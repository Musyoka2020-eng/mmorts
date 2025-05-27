<?php

/**
 * Battle Manager Class
 * 
 * Handles combat between player and AI armies.
 */
class BattleManager
{
    private $conn;
    private const MAX_ROUNDS = 6; // Define maximum number of rounds
    private $attackerMorale; // Stores current attacker morale during a battle
    private $defenderMorale; // Stores current defender morale during a battle
    private $unitPowerValues = [ // Moved here to be accessible by calculateArmyPower
        'fighters' => 1,
        'shooters' => 1.2,
        'vehicles' => 3,
        'skirmishers' => 0.8,
        'riders' => 2,
        'canons' => 5,
        'jets' => 4,
        'archers' => 1.5,
        'marauders' => 2.5
    ];

    /**
     * Constructor
     *
     * @param mysqli $conn Database connection
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Initialize unit stats, including morale
     */
    private function initializeUnitStats(&$army) // Pass by reference
    {
        // Ensure $army is an array
        if (!is_array($army)) {
            // Handle error or set to default empty army structure
            // For now, let's assume getArmies returns a valid structure or an empty one that needs initialization
            $army = [
                'fighters' => 0,
                'shooters' => 0,
                'vehicles' => 0,
                'skirmishers' => 0,
                'riders' => 0,
                'canons' => 0,
                'jets' => 0,
                'archers' => 0,
                'marauders' => 0
            ];
        }
        // Add morale to each unit type if not already present (though morale is army-wide for now)
        // This structure might be useful if we go for per-unit morale later.
        // For now, army-wide morale is handled by $this->attackerMorale and $this->defenderMorale.
        // We can add 'initial_morale' or other stats here if needed.
        // Example: $army['morale_level'] = 100; // Army-wide morale, perhaps stored with army data
    }

    /**
     * Initiate a battle between two armies
     *
     * @param int $attackerId ID of the attacker
     * @param string $attackerType Type of attacker (player, ai, npc)
     * @param int $defenderId ID of the defender
     * @param string $defenderType Type of defender (player, ai, npc)
     * @param string $attackerStrategy Attacker's chosen strategy
     * @return array Battle result
     */
    public function initiateBattle($attackerId, $attackerType, $defenderId, $defenderType, $attackerStrategy = 'balanced')
    {
        try {
            // Validate parameters
            if (!$attackerId || !$attackerType || !$defenderId || !$defenderType) {
                throw new Exception("Invalid battle parameters provided");
            }

            // Get attacker armies
            $attackerArmies = $this->getArmies($attackerId, $attackerType);
            if (empty($attackerArmies) || $this->getTotalUnits($attackerArmies) <= 0) {
                throw new Exception("Attacker has no units to battle with");
            }
            $this->initializeUnitStats($attackerArmies);

            // Get defender armies
            $defenderArmies = $this->getArmies($defenderId, $defenderType);
            if (empty($defenderArmies) || $this->getTotalUnits($defenderArmies) <= 0) {
                throw new Exception("Defender has no units to battle with");
            }
            $this->initializeUnitStats($defenderArmies);

            $this->attackerMorale = 100;
            $this->defenderMorale = 100;

            $initialAttackerArmies = $attackerArmies;
            $initialDefenderArmies = $defenderArmies;

            $battleLog = [];
            $attackerOverallRouted = false;
            $defenderOverallRouted = false;

            $this->conn->autocommit(false);

            for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
                if ($this->getTotalUnits($attackerArmies) <= 0 || $this->getTotalUnits($defenderArmies) <= 0) {
                    break;
                }
                // If an army is already considered routed from a previous round, it might affect the next round's start
                // The simulateRound function now takes current round number and handles rout status internally.
                $roundResult = $this->simulateRound($attackerArmies, $defenderArmies, $attackerStrategy, $this->attackerMorale, $this->defenderMorale, $round);

                $attackerArmies = $roundResult['attacker_remaining_round'];
                $defenderArmies = $roundResult['defender_remaining_round'];

                $this->attackerMorale = $roundResult['attacker_morale_after_round'];
                $this->defenderMorale = $roundResult['defender_morale_after_round'];

                // Update overall rout status
                if ($roundResult['attacker_routed_this_round']) $attackerOverallRouted = true;
                if ($roundResult['defender_routed_this_round']) $defenderOverallRouted = true;

                $battleLogEntry = [
                    'round' => $round,
                    'attacker_losses_round' => $roundResult['attacker_losses_round'],
                    'defender_losses_round' => $roundResult['defender_losses_round'],
                    'attacker_morale' => $this->attackerMorale,
                    'defender_morale' => $this->defenderMorale,
                    'attacker_power_round' => $roundResult['attacker_power_round'],
                    'defender_power_round' => $roundResult['defender_power_round'],
                    'attacker_routed' => $roundResult['attacker_routed_this_round'],
                    'defender_routed' => $roundResult['defender_routed_this_round'],
                ];
                $battleLog[] = $battleLogEntry;

                // End battle if both are routed or one is wiped out while the other is routed, or morale is 0.
                if (($attackerOverallRouted && $defenderOverallRouted) ||
                    ($this->getTotalUnits($attackerArmies) <= 0 && $defenderOverallRouted) ||
                    ($this->getTotalUnits($defenderArmies) <= 0 && $attackerOverallRouted) ||
                    $this->attackerMorale <= 0 || $this->defenderMorale <= 0
                ) {
                    break;
                }
            }

            // Calculate total losses
            $totalAttackerLosses = $this->calculateTotalLosses($initialAttackerArmies, $attackerArmies);
            $totalDefenderLosses = $this->calculateTotalLosses($initialDefenderArmies, $defenderArmies);

            // Determine final battle result
            $finalResult = $this->determineFinalBattleResult($attackerArmies, $defenderArmies, $initialAttackerArmies, $initialDefenderArmies, $this->attackerMorale, $this->defenderMorale);

            // Calculate plundered resources
            $resourcesPlundered = [];
            if ($finalResult === 'attacker_victory') {
                // Use initial defender power for plunder calculation, or current if more appropriate
                $initialDefenderPower = $this->calculateArmyPower($initialDefenderArmies, true);
                $initialAttackerPower = $this->calculateArmyPower($initialAttackerArmies, false, $attackerStrategy);
                $resourcesPlundered = $this->calculatePlunderedResources($initialDefenderPower, $initialAttackerPower); // Consider if this needs defender's remaining resources
            }

            // Record battle in database (consider what to store from battleLog)
            $this->recordBattle(
                $attackerId,
                $attackerType,
                $defenderId,
                $defenderType,
                $finalResult,
                $totalAttackerLosses,
                $totalDefenderLosses,
                $resourcesPlundered, // Pass the calculated plundered resources
                json_encode($battleLog) // Store round-by-round log
            );

            // Update armies after battle
            $this->updateArmiesAfterBattle($attackerId, $attackerType, $attackerArmies);
            $this->updateArmiesAfterBattle($defenderId, $defenderType, $defenderArmies);

            // If attacker won, transfer resources
            if ($finalResult === 'attacker_victory') {
                $this->transferResources($attackerId, $attackerType, $defenderId, $defenderType, $resourcesPlundered);
            }

            // Commit the transaction
            $this->conn->commit();

            return [
                'result' => $finalResult,
                'attacker_losses' => $totalAttackerLosses,
                'defender_losses' => $totalDefenderLosses,
                'attacker_remaining' => $attackerArmies,
                'defender_remaining' => $defenderArmies,
                'resources_plundered' => $resourcesPlundered,
                'battle_log' => $battleLog,
                'attacker_morale_final' => $this->attackerMorale,
                'defender_morale_final' => $this->defenderMorale
            ];
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            try {
                $this->conn->rollback();
            } catch (Exception $rollbackEx) {
                // Rollback might fail if no transaction was active
                error_log("Battle rollback error: " . $rollbackEx->getMessage());
            }

            // Log the error
            error_log("Battle error: " . $e->getMessage());

            // Rethrow it
            throw $e;
        }
    }

    /**
     * Get armies for a player or AI
     * 
     * @param int $id Player or AI ID
     * @param string $type Type (player, ai, npc)
     * @return array Army data
     */
    private function getArmies($id, $type)
    {
        if ($type === 'player') {
            // Get player armies
            $query = "SELECT fighters, shooters, vehicles, skirmishers, riders, canons, jets, archers, marauders 
                     FROM player_armies WHERE player_id = ?";
        } else if ($type === 'ai') {
            // Get AI armies
            $query = "SELECT fighters, shooters, vehicles, skirmishers, riders, canons, jets, archers, marauders 
                     FROM ai_armies WHERE ai_city_id = ?";
        } else {
            // Default empty armies
            return [
                'fighters' => 0,
                'shooters' => 0,
                'vehicles' => 0,
                'skirmishers' => 0,
                'riders' => 0,
                'canons' => 0,
                'jets' => 0,
                'archers' => 0,
                'marauders' => 0
            ];
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        } else {
            // Default empty armies
            return [
                'fighters' => 0,
                'shooters' => 0,
                'vehicles' => 0,
                'skirmishers' => 0,
                'riders' => 0,
                'canons' => 0,
                'jets' => 0,
                'archers' => 0,
                'marauders' => 0
            ];
        }
    }

    /**
     * Calculate total losses by comparing initial and final army compositions.
     */
    private function calculateTotalLosses($initialArmies, $finalArmies)
    {
        $totalLosses = [];
        // Ensure inputs are arrays
        if (!is_array($initialArmies)) $initialArmies = [];
        if (!is_array($finalArmies)) $finalArmies = [];

        // Define all possible unit types to ensure they are all present in the output, even if with 0 losses.
        $allUnitTypes = ['fighters', 'shooters', 'vehicles', 'skirmishers', 'riders', 'canons', 'jets', 'archers', 'marauders'];

        foreach ($allUnitTypes as $unitType) {
            $initialCount = isset($initialArmies[$unitType]) && is_numeric($initialArmies[$unitType]) ? intval($initialArmies[$unitType]) : 0;
            $finalCount = isset($finalArmies[$unitType]) && is_numeric($finalArmies[$unitType]) ? intval($finalArmies[$unitType]) : 0;
            $totalLosses[$unitType] = max(0, $initialCount - $finalCount);
        }

        error_log("Calculated Total Losses: " . json_encode($totalLosses)); // Log calculated losses
        return $totalLosses;
    }

    /**
     * Determine the final result of the battle after all rounds.
     */
    private function determineFinalBattleResult($attackerArmies, $defenderArmies, $initialAttackerArmies, $initialDefenderArmies, $attackerMorale, $defenderMorale)
    {
        $attackerUnitsRemaining = $this->getTotalUnits($attackerArmies);
        $defenderUnitsRemaining = $this->getTotalUnits($defenderArmies);
        $initialAttackerUnits = $this->getTotalUnits($initialAttackerArmies);
        $initialDefenderUnits = $this->getTotalUnits($initialDefenderArmies);

        // Calculate percentage of forces remaining
        $attackerPercentRemaining = ($initialAttackerUnits > 0) ? ($attackerUnitsRemaining / $initialAttackerUnits) * 100 : 0;
        $defenderPercentRemaining = ($initialDefenderUnits > 0) ? ($defenderUnitsRemaining / $initialDefenderUnits) * 100 : 0;

        if ($attackerUnitsRemaining > 0 && $defenderUnitsRemaining <= 0) {
            return 'attacker_victory'; // Defender wiped out
        }
        if ($defenderUnitsRemaining > 0 && $attackerUnitsRemaining <= 0) {
            return 'defender_victory'; // Attacker wiped out
        }
        if ($attackerUnitsRemaining <= 0 && $defenderUnitsRemaining <= 0) {
            return 'draw'; // Mutual annihilation
        }

        // If battle ends due to rounds limit with units on both sides
        // Consider morale and remaining strength
        if ($attackerMorale <= 20 && $defenderMorale > 20) return 'defender_victory'; // Attacker routed
        if ($defenderMorale <= 20 && $attackerMorale > 20) return 'attacker_victory'; // Defender routed

        // Compare remaining forces as a primary factor if morale isn't decisive
        if ($attackerPercentRemaining > $defenderPercentRemaining * 1.1) { // Attacker has significantly more % of forces left
            return 'attacker_victory';
        } elseif ($defenderPercentRemaining > $attackerPercentRemaining * 1.1) { // Defender has significantly more %
            return 'defender_victory';
        }

        // If still close, consider raw numbers or a draw
        if ($attackerUnitsRemaining > $defenderUnitsRemaining) {
            // return 'attacker_victory'; // Minor victory
        } elseif ($defenderUnitsRemaining > $attackerUnitsRemaining) {
            // return 'defender_victory'; // Minor victory
        }

        return 'draw'; // Default to draw if conditions are too close or ambiguous
    }


    /**
     * Simulate a single round of combat.
     * This will incorporate morale effects and calculate losses for the round.
     *
     * @param array &$attackerArmies Current attacker armies (passed by reference)
     * @param array &$defenderArmies Current defender armies (passed by reference)
     * @param string $attackerStrategy Attacker's strategy
     * @param float &$attackerMorale Current attacker morale (passed by reference)
     * @param float &$defenderMorale Current defender morale (passed by reference)
     * @param int $currentRound The current round number
     * @return array Results of the round (losses, remaining units, new morale, rout status)
     */
    private function simulateRound(&$attackerArmies, &$defenderArmies, $attackerStrategy, &$attackerMorale, &$defenderMorale, $currentRound)
    {
        $attackerRouted = false;
        $defenderRouted = false;
        $routThreshold = 10; // Morale below this may cause a rout

        // Pre-combat rout check
        if ($attackerMorale < $routThreshold) $attackerRouted = true;
        if ($defenderMorale < $routThreshold) $defenderRouted = true;

        // 1. Calculate Army Power (factoring in current morale and rout status)
        $attackerPower = $this->calculateArmyPower($attackerArmies, false, $attackerStrategy, $attackerMorale, $attackerRouted);
        $defenderPower = $this->calculateArmyPower($defenderArmies, true, null, $defenderMorale, $defenderRouted);

        // 2. Calculate Losses for the round
        $baseAttackerLossRate = $defenderPower / (max(1, $attackerPower) * 1.5);
        $baseDefenderLossRate = $attackerPower / (max(1, $defenderPower) * 1.2);

        $attackerLossRateModifier = 1.0;
        if ($attackerStrategy === 'aggressive') $attackerLossRateModifier = 1.15;
        elseif ($attackerStrategy === 'defensive') $attackerLossRateModifier = 0.80;

        $currentAttackerLossRate = min(0.8, $baseAttackerLossRate * $attackerLossRateModifier);
        $currentDefenderLossRate = min(0.8, $baseDefenderLossRate);

        // If an army is routed, they take significantly more losses and inflict less
        if ($attackerRouted) {
            $currentAttackerLossRate *= 2;
            $currentDefenderLossRate *= 0.5;
        }
        if ($defenderRouted) {
            $currentDefenderLossRate *= 2;
            $currentAttackerLossRate *= 0.5;
        }
        $currentAttackerLossRate = min(1.0, $currentAttackerLossRate);
        $currentDefenderLossRate = min(1.0, $currentDefenderLossRate);


        $attackerLossesRound = $this->calculateArmyLosses($attackerArmies, $currentAttackerLossRate);
        $defenderLossesRound = $this->calculateArmyLosses($defenderArmies, $currentDefenderLossRate);

        // 3. Update Armies for the round
        $attackerRemainingRound = $this->calculateRemainingArmies($attackerArmies, $attackerLossesRound);
        $defenderRemainingRound = $this->calculateRemainingArmies($defenderArmies, $defenderLossesRound);

        // Store armies before morale update for accurate morale calculation input
        $attackerArmiesBeforeMoraleUpdate = $attackerArmies; // These are armies at START of round for morale calc context
        $defenderArmiesBeforeMoraleUpdate = $defenderArmies; // These are armies at START of round for morale calc context

        // 4. Update Morale based on round events
        $this->updateMorale($attackerMorale, $attackerLossesRound, $defenderLossesRound, $attackerArmiesBeforeMoraleUpdate, $defenderArmiesBeforeMoraleUpdate, true, $currentRound);
        $this->updateMorale($defenderMorale, $defenderLossesRound, $attackerLossesRound, $defenderArmiesBeforeMoraleUpdate, $attackerArmiesBeforeMoraleUpdate, false, $currentRound);

        // Post-combat rout check based on new morale
        if ($attackerMorale < $routThreshold && !$attackerRouted) $attackerRouted = true;
        if ($defenderMorale < $routThreshold && !$defenderRouted) $defenderRouted = true;


        return [
            'attacker_losses_round' => $attackerLossesRound,
            'defender_losses_round' => $defenderLossesRound,
            'attacker_remaining_round' => $attackerRemainingRound,
            'defender_remaining_round' => $defenderRemainingRound,
            'attacker_morale_after_round' => $attackerMorale,
            'defender_morale_after_round' => $defenderMorale,
            'attacker_power_round' => $attackerPower,
            'defender_power_round' => $defenderPower,
            'attacker_routed_this_round' => $attackerRouted,
            'defender_routed_this_round' => $defenderRouted,
        ];
    }

    /**
     * Update morale based on combat events.
     *
     * @param float &$morale Current morale of the side (passed by reference)
     * @param array $ownLosses Losses sustained by this side in the round
     * @param array $enemyLosses Losses inflicted on the enemy in the round
     * @param array $ownArmyInitialInRound Current state of own army (at start of this round)
     * @param array $enemyArmyInitialInRound Current state of enemy army (at start of this round)
     * @param bool $isAttacker Flag to differentiate attacker/defender specific logic if any
     * @param int $currentRound The current round number
     */
    private function updateMorale(&$morale, $ownLosses, $enemyLosses, $ownArmyInitialInRound, $enemyArmyInitialInRound, $isAttacker, $currentRound)
    {
        $totalOwnUnitsInitial = max(1, $this->getTotalUnits($ownArmyInitialInRound));
        $totalEnemyUnitsInitial = max(1, $this->getTotalUnits($enemyArmyInitialInRound));

        $ownCasualtiesCount = $this->getTotalUnits($ownLosses);
        $enemyCasualtiesCount = $this->getTotalUnits($enemyLosses);

        $ownCasualtyRate = $ownCasualtiesCount / $totalOwnUnitsInitial;
        $enemyCasualtyRate = $enemyCasualtiesCount / $totalEnemyUnitsInitial;

        $baseMoraleChange = 0;

        // 1. Impact of Casualty Exchange
        if ($ownCasualtyRate > $enemyCasualtyRate + 0.05) {
            $baseMoraleChange -= ($ownCasualtyRate - $enemyCasualtyRate) * 40;
        } elseif ($enemyCasualtyRate > $ownCasualtyRate + 0.05) {
            $baseMoraleChange += ($enemyCasualtyRate - $ownCasualtyRate) * 30;
        } else {
            $baseMoraleChange -= $ownCasualtyRate * 20;
        }

        if ($ownCasualtyRate > 0.3) {
            $baseMoraleChange -= 10;
        }
        if ($ownCasualtyRate > 0.5) {
            $baseMoraleChange -= 15;
        }

        if ($enemyCasualtyRate > 0.3 && $ownCasualtyRate < 0.1) {
            $baseMoraleChange += 10;
        }

        // 2. Morale State Modifier (Resilience/Fragility)
        if ($morale > 75) {
            if ($baseMoraleChange < 0) $baseMoraleChange *= 0.7;
        } elseif ($morale < 25) {
            if ($baseMoraleChange < 0) $baseMoraleChange *= 1.5;
            $baseMoraleChange -= 2;
        } elseif ($morale < 50) {
            if ($baseMoraleChange < 0) $baseMoraleChange *= 1.2;
        }

        // 3. Perceived Strength Difference
        $ownPowerForPerception = $this->calculateArmyPower($ownArmyInitialInRound, !$isAttacker, null, $morale, false); // Use current morale, assume not routed for this perception check
        $enemyPowerForPerception = $this->calculateArmyPower($enemyArmyInitialInRound, $isAttacker, null, 100, false); // Assume enemy at ideal state for perception

        if ($ownPowerForPerception < $enemyPowerForPerception * 0.6) {
            $baseMoraleChange -= 2;
        } elseif ($ownPowerForPerception > $enemyPowerForPerception * 1.5) {
            $baseMoraleChange += 1;
        } else {
            if ($currentRound > 2 && $baseMoraleChange <= 0) $baseMoraleChange -= 0.5;
        }

        $morale += $baseMoraleChange;

        $morale = max(0, min(100, $morale));
    }


    /**
     * Calculate army power
     * 
     * @param array $armies Army data
     * @param bool $isDefender Whether this is the defender (gets defensive bonus)
     * @param string|null $strategy Attacker's strategy (if applicable)
     * @param float|null $morale Current morale of the army (0-100)
     * @param bool $isRouted Whether the army is currently routed
     * @return float Total army power
     */
    private function calculateArmyPower($armies, $isDefender = false, $strategy = null, $morale = null, $isRouted = false)
    {
        if ($isRouted) {
            // Severely reduce power if routed, representing disorganization and unwillingness to fight.
            $basePower = 0;
            // Ensure $armies is an array before iterating
            if (!is_array($armies)) $armies = []; // Default to empty array if not set properly
            foreach ($armies as $unit => $count) {
                if (isset($this->unitPowerValues[$unit])) {
                    $basePower += intval($count) * $this->unitPowerValues[$unit];
                }
            }
            return max(1, $basePower * 0.1); // Routed units have only 10% of their power
        }

        // Unit power values are now a class property: $this->unitPowerValues
        $totalPower = 0;
        // Ensure $armies is an array before iterating
        if (!is_array($armies)) $armies = []; // Default to empty array
        foreach ($armies as $unit => $count) {
            if (isset($this->unitPowerValues[$unit])) {
                $totalPower += intval($count) * $this->unitPowerValues[$unit];
            }
        }

        // Apply strategy modifiers for attacker
        if (!$isDefender && $strategy !== null) {
            switch ($strategy) {
                case 'aggressive':
                    $totalPower *= 1.25;
                    break;
                case 'defensive':
                    $totalPower *= 0.90;
                    break;
                case 'balanced':
                    $totalPower *= 1.10;
                    break;
            }
        }

        // Defenders get a defensive bonus
        if ($isDefender) {
            $totalPower *= 1.20;
        }

        // Apply morale effects on power
        if ($morale !== null) {
            $moraleModifier = 1.0;
            if ($morale >= 80) { // Confident / High Morale
                $moraleModifier = 1.0 + (($morale - 80) / 20) * 0.15; // Max 1.15 at 100 morale
            } elseif ($morale >= 50) { // Normal / Steady Morale
                $moraleModifier = 0.85 + (($morale - 50) / 30) * 0.15; // Scales from 0.85 at 50 to 1.0 at 80
            } elseif ($morale >= 20) { // Wavering Morale
                $moraleModifier = 0.50 + (($morale - 20) / 30) * 0.35; // Scales from 0.50 at 20 to 0.85 at 50
            } else { // Low / Broken Morale (0-19)
                $moraleModifier = 0.20 + ($morale / 20) * 0.30;        // Scales from 0.20 at 0 to 0.50 at 20
            }
            $totalPower *= $moraleModifier;
        }

        return max(1, $totalPower); // Ensure power is at least 1
    }

    /**
     * Calculate army losses
     * 
     * @param array $armies Army data
     * @param float $lossRate Rate of losses (0-1)
     * @return array Army losses
     */
    private function calculateArmyLosses($armies, $lossRate)
    {
        $losses = [];
        foreach ($armies as $unit => $count) {
            // Add some randomness to losses
            $actualLossRate = $lossRate * (0.8 + (mt_rand(0, 40) / 100));
            $losses[$unit] = round($count * $actualLossRate);
        }
        return $losses;
    }

    /**
     * Calculate remaining armies after battle
     * 
     * @param array $armies Original army data
     * @param array $losses Army losses
     * @return array Remaining armies
     */
    private function calculateRemainingArmies($armies, $losses)
    {
        $remaining = [];
        foreach ($armies as $unit => $count) {
            $remaining[$unit] = max(0, $count - $losses[$unit]);
        }
        return $remaining;
    }

    /**
     * Determine the result of the battle
     * 
     * @param float $attackerPower Attacker power
     * @param float $defenderPower Defender power
     * @param float $attackerLossRate Attacker loss rate
     * @param float $defenderLossRate Defender loss rate
     * @return string Battle result
     */
    private function determineBattleResult($attackerPower, $defenderPower, $attackerLossRate, $defenderLossRate)
    {
        if ($attackerPower > $defenderPower * 1.1 && $attackerLossRate < $defenderLossRate) {
            return 'attacker_victory';
        } elseif ($defenderPower > $attackerPower * 0.9 || $attackerLossRate > $defenderLossRate * 1.2) {
            return 'defender_victory';
        } else {
            return 'draw';
        }
    }

    /**
     * Calculate resources plundered after a victory
     * 
     * @param float $defenderPower Defender power
     * @param float $attackerPower Attacker power
     * @return array Plundered resources
     */
    private function calculatePlunderedResources($defenderPower, $attackerPower)
    {
        // Base plunder rate is 30% of defender's resources
        $plunderRate = 0.3;

        // Increase plunder rate if attacker is much stronger
        if ($attackerPower > $defenderPower * 2) {
            $plunderRate = 0.5;
        }

        return [
            'wood' => round(1000 * $plunderRate),
            'oil' => round(800 * $plunderRate),
            'iron' => round(600 * $plunderRate),
            'food' => round(1200 * $plunderRate),
            'stone' => round(700 * $plunderRate)
        ];
    }

    /**
     * Record battle in database
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param string $result Battle result
     * @param array $attackerLosses Attacker losses
     * @param array $defenderLosses Defender losses
     * @param array $resourcesPlundered Resources plundered
     * @param string $battleLogJson JSON string of the battle log (round by round)
     */
    private function recordBattle($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered, $battleLogJson = '[]')
    {
        // Ensure data is valid before processing
        if (!is_array($attackerLosses) || empty($attackerLosses)) {
            $attackerLosses = array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skirmishers" => 0,
                "riders" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }
        if (!is_array($defenderLosses) || empty($defenderLosses)) {
            $defenderLosses = array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skirmishers" => 0,
                "riders" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }
        if (!is_array($resourcesPlundered) || empty($resourcesPlundered)) {
            $resourcesPlundered = array("wood" => 0, "oil" => 0, "iron" => 0, "food" => 0, "stone" => 0);
        }        // Clean and normalize the data to ensure MySQL JSON compatibility
        // Make sure all values are proper numeric types, not strings or other types
        $attackerLosses = $this->sanitizeArrayForJson($attackerLosses);
        $defenderLosses = $this->sanitizeArrayForJson($defenderLosses);
        $resourcesPlundered = $this->sanitizeArrayForJson($resourcesPlundered);

        // Ensure JSON encoding doesn't fail - use JSON_NUMERIC_CHECK for numeric values
        // Additionally use JSON_UNESCAPED_UNICODE to avoid unnecessary escaping
        $jsonOptions = JSON_FORCE_OBJECT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE;

        $attackerLossesJson = json_encode($attackerLosses, $jsonOptions);
        error_log("Attacker losses JSON: " . $attackerLossesJson);

        $defenderLossesJson = json_encode($defenderLosses, $jsonOptions);
        error_log("Defender losses JSON: " . $defenderLossesJson);

        $resourcesPlunderedJson = json_encode($resourcesPlundered, $jsonOptions);
        error_log("Resources plundered JSON: " . $resourcesPlunderedJson);

        // Ensure we have valid JSON for database - provide valid fallbacks
        if ($attackerLossesJson === false) {
            error_log("Failed to encode attacker losses: " . json_last_error_msg());
            $attackerLossesJson = '{"error":0}';
        }
        if ($defenderLossesJson === false) {
            error_log("Failed to encode defender losses: " . json_last_error_msg());
            $defenderLossesJson = '{"error":0}';
        }
        if ($resourcesPlunderedJson === false) {
            error_log("Failed to encode resources plundered: " . json_last_error_msg());
            $resourcesPlunderedJson = '{"wood":0,"oil":0,"iron":0,"food":0,"stone":0}';
        }
        // Generate a battle report (this might need to be enhanced to use the battleLogJson)
        $report = $this->generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered, $battleLogJson);

        // Verify JSON validity one more time
        if (!$this->isValidJson($attackerLossesJson)) {
            $attackerLossesJson = '{"error":"Invalid attacker losses JSON"}'; // More descriptive default
            error_log("Invalid JSON detected for attacker losses, using default: " . $attackerLossesJson);
        }
        if (!$this->isValidJson($defenderLossesJson)) {
            $defenderLossesJson = '{"error":"Invalid defender losses JSON"}'; // More descriptive default
            error_log("Invalid JSON detected for defender losses, using default: " . $defenderLossesJson);
        }
        if (!$this->isValidJson($resourcesPlunderedJson)) {
            $resourcesPlunderedJson = '{"error":"Invalid resources plundered JSON"}'; // More descriptive default
            error_log("Invalid JSON detected for resources plundered, using default: " . $resourcesPlunderedJson);
        }
        if (!$this->isValidJson($battleLogJson)) {
            $battleLogJson = '[{"error":"Invalid battle log JSON"}]'; // More descriptive default
            error_log("Invalid JSON detected for battle log, using default: " . $battleLogJson);
        }

        // Use CAST to ensure proper JSON formatting for all JSON columns
        // Added battle_log column to the query
        $query = "INSERT INTO battles (attacker_id, attacker_type, defender_id, defender_type, battle_result,
                 attacker_units_lost, defender_units_lost, resources_plundered, battle_report, battle_log)
                 VALUES (?, ?, ?, ?, ?,
                 CAST(? AS JSON), CAST(? AS JSON), CAST(? AS JSON), ?, CAST(? AS JSON))";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "isisssssss", // Added 's' for battle_log
            $attackerId,
            $attackerType,
            $defenderId,
            $defenderType,
            $result,
            $attackerLossesJson,
            $defenderLossesJson,
            $resourcesPlunderedJson,
            $report,
            $battleLogJson // Bind the battle log
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to record battle: " . $stmt->error);
        }

        // Return the battle ID from the inserted record
        return $this->conn->insert_id;
    }
    /**
     * Count the total units in an army
     * 
     * @param array $armies Army data
     * @return int Total units count
     */
    private function getTotalUnits($armies)
    {
        $total = 0;
        foreach ($armies as $unit => $count) {
            $total += $count;
        }
        return $total;
    }

    /**
     * Generate a battle report
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param string $result Battle result
     * @param array $attackerLosses Attacker total losses
     * @param array $defenderLosses Defender total losses
     * @param array $resourcesPlundered Resources plundered
     * @param string $battleLogJson JSON string of the battle log
     * @return string Battle report HTML
     */
    private function generateBattleReport($attackerId, $attackerType, $defenderId, $defenderType, $result, $attackerLosses, $defenderLosses, $resourcesPlundered, $battleLogJson = '[]')
    {
        // Get attacker and defender names
        $attackerName = $this->getEntityName($attackerId, $attackerType);
        $defenderName = $this->getEntityName($defenderId, $defenderType);

        // Create HTML battle report
        $report = '<div class="battle-report">';
        $report .= '<h2>Battle Report Summary</h2>';
        $report .= "<p><strong>Outcome:</strong> " . ucfirst(str_replace('_', ' ', $result)) . "</p>";

        // Display total losses
        $report .= '<h3>Total Attacker Losses:</h3><ul>';
        foreach ($attackerLosses as $unit => $lost) {
            if ($lost > 0) $report .= "<li>" . ucfirst($unit) . ": " . htmlspecialchars($lost) . "</li>";
        }
        $report .= '</ul>';

        $report .= '<h3>Total Defender Losses:</h3><ul>';
        foreach ($defenderLosses as $unit => $lost) {
            if ($lost > 0) $report .= "<li>" . ucfirst($unit) . ": " . htmlspecialchars($lost) . "</li>";
        }
        $report .= '</ul>';

        if (!empty($resourcesPlundered) && array_sum($resourcesPlundered) > 0) {
            $report .= '<h3>Resources Plundered:</h3><ul>';
            foreach ($resourcesPlundered as $resource => $amount) {
                if ($amount > 0) $report .= "<li>" . ucfirst($resource) . ": " . htmlspecialchars($amount) . "</li>";
            }
            $report .= '</ul>';
        } else if ($result === 'attacker_victory') {
            $report .= '<p>No resources were plundered.</p>';
        }

        // Detailed Round-by-Round Log (optional, can be extensive)
        $logData = json_decode($battleLogJson, true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($logData)) {
            $report .= '<h3>Round Details:</h3>';
            foreach ($logData as $roundEntry) {
                $report .= '<div class="round-entry">';
                $report .= "<h4>Round " . htmlspecialchars($roundEntry['round']) . "</h4>";
                $report .= "<p>Attacker Morale: " . htmlspecialchars(round($roundEntry['attacker_morale'], 1)) . " | Defender Morale: " . htmlspecialchars(round($roundEntry['defender_morale'], 1)) . "</p>";

                $report .= '<h5>Attacker Losses This Round:</h5><ul>';
                $attackerRoundLosses = 0;
                foreach ($roundEntry['attacker_losses_round'] as $unit => $lost) {
                    if ($lost > 0) {
                        $report .= "<li>" . ucfirst($unit) . ": " . htmlspecialchars($lost) . "</li>";
                        $attackerRoundLosses += $lost;
                    }
                }
                if ($attackerRoundLosses == 0) $report .= "<li>No losses</li>";
                $report .= '</ul>';

                $report .= '<h5>Defender Losses This Round:</h5><ul>';
                $defenderRoundLosses = 0;
                foreach ($roundEntry['defender_losses_round'] as $unit => $lost) {
                    if ($lost > 0) {
                        $report .= "<li>" . ucfirst($unit) . ": " . htmlspecialchars($lost) . "</li>";
                        $defenderRoundLosses += $lost;
                    }
                }
                if ($defenderRoundLosses == 0) $report .= "<li>No losses</li>";
                $report .= '</ul>';
                $report .= '</div>'; // end round-entry
            }
        } else {
            $report .= "<p>No detailed round log available or error decoding log.</p>";
            error_log("Battle report: Error decoding battle log JSON or log is empty. JSON: " . $battleLogJson . " Error: " . json_last_error_msg());
        }
        $report .= '<div class="battle-timestamp">' . date('F j, Y, g:i a') . '</div>';

        // Participants
        $report .= '<div class="battle-participants">';
        $report .= '<div class="participant attacker"><strong>Attacker:</strong> ' . htmlspecialchars($attackerName) . '</div>';
        $report .= '<div class="participant defender"><strong>Defender:</strong> ' . htmlspecialchars($defenderName) . '</div>';
        $report .= '</div>';

        // Result
        $report .= '<div class="battle-result">';
        if ($result === 'attacker_victory') {
            $report .= '<div class="result victory">The attacker was victorious!</div>';
        } elseif ($result === 'defender_victory') {
            $report .= '<div class="result defeat">The defender successfully repelled the attack!</div>';
        } else {
            $report .= '<div class="result draw">The battle ended in a draw.</div>';
        }
        $report .= '</div>';

        // Losses
        $report .= '<div class="battle-losses">';
        $report .= '<h3>Casualties</h3>';

        // Attacker losses
        $report .= '<div class="losses attacker-losses">';
        $report .= '<h4>Attacker Losses:</h4>';
        $report .= '<ul>';
        $hasAttackerLosses = false;
        foreach ($attackerLosses as $unit => $count) {
            if ($count > 0) {
                $hasAttackerLosses = true;
                $report .= '<li>' . htmlspecialchars($unit) . ': <span class="loss-count">' . $count . '</span></li>';
            }
        }
        if (!$hasAttackerLosses) {
            $report .= '<li>No units were lost</li>';
        }
        $report .= '</ul>';
        $report .= '</div>';

        // Defender losses
        $report .= '<div class="losses defender-losses">';
        $report .= '<h4>Defender Losses:</h4>';
        $report .= '<ul>';
        $hasDefenderLosses = false;
        foreach ($defenderLosses as $unit => $count) {
            if ($count > 0) {
                $hasDefenderLosses = true;
                $report .= '<li>' . htmlspecialchars($unit) . ': <span class="loss-count">' . $count . '</span></li>';
            }
        }
        if (!$hasDefenderLosses) {
            $report .= '<li>No units were lost</li>';
        }
        $report .= '</ul>';
        $report .= '</div>';
        $report .= '</div>';
        // Plundered resources - show regardless of battle outcome if there are resources
        $hasPlunder = false;
        foreach ($resourcesPlundered as $resource => $amount) {
            if ($amount > 0) {
                $hasPlunder = true;
                break;
            }
        }

        if ($hasPlunder) {
            $report .= '<div class="plundered-resources">';
            $report .= '<h3>Resources Plundered</h3>';
            $report .= '<ul>';
            foreach ($resourcesPlundered as $resource => $amount) {
                if ($amount > 0) {
                    $report .= '<li>' . htmlspecialchars($resource) . ': <span class="resource-amount">' . $amount . '</span></li>';
                }
            }
            $report .= '</ul>';
            $report .= '</div>';
        } else {
            $report .= '<div class="plundered-resources">';
            $report .= '<h3>Resources</h3>';
            $report .= '<ul>';
            $report .= '<li>No resources were plundered</li>';
            $report .= '</ul>';
            $report .= '</div>';
        }

        $report .= '</div>';

        return $report;
    }

    /**
     * Get entity name
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @return string Entity name
     */
    private function getEntityName($id, $type)
    {
        if ($type === 'player') {
            $query = "SELECT username FROM players WHERE id = ?";
        } elseif ($type === 'ai') {
            $query = "SELECT name FROM ai_cities WHERE id = ?";
        } else {
            return "Unknown Entity";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['username'] ?? $row['name'] ?? "Unknown Entity";
        } else {
            return "Unknown Entity";
        }
    }

    /**
     * Update armies after battle
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @param array $armies Remaining armies
     */
    private function updateArmiesAfterBattle($id, $type, $armies)
    {
        if ($type === 'player') {
            $query = "UPDATE player_armies SET 
                     fighters = ?, 
                     shooters = ?, 
                     vehicles = ?, 
                     skirmishers = ?, 
                     riders = ?, 
                     canons = ?, 
                     jets = ?, 
                     archers = ?, 
                     marauders = ? 
                     WHERE player_id = ?";
        } elseif ($type === 'ai') {
            $query = "UPDATE ai_armies SET 
                     fighters = ?, 
                     shooters = ?, 
                     vehicles = ?, 
                     skirmishers = ?, 
                     riders = ?, 
                     canons = ?, 
                     jets = ?, 
                     archers = ?, 
                     marauders = ? 
                     WHERE ai_city_id = ?";
        } else {
            return;
        }
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiiiiiii",
            $armies['fighters'],
            $armies['shooters'],
            $armies['vehicles'],
            $armies['skirmishers'],
            $armies['riders'],
            $armies['canons'],
            $armies['jets'],
            $armies['archers'],
            $armies['marauders'],
            $id
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update armies after battle: " . $stmt->error);
        }
    }

    /**
     * Transfer resources from defender to attacker
     * 
     * @param int $attackerId Attacker ID
     * @param string $attackerType Attacker type
     * @param int $defenderId Defender ID
     * @param string $defenderType Defender type
     * @param array $resources Resources to transfer
     */
    private function transferResources($attackerId, $attackerType, $defenderId, $defenderType, $resources)
    {
        // Get resource IDs
        $attackerResourcesId = $this->getResourcesId($attackerId, $attackerType);
        $defenderResourcesId = $this->getResourcesId($defenderId, $defenderType);

        if (!$attackerResourcesId || !$defenderResourcesId) {
            return;
        }

        // Update defender resources (subtract)
        $query = "UPDATE resources SET 
                 wood = GREATEST(0, wood - ?), 
                 oil = GREATEST(0, oil - ?), 
                 iron = GREATEST(0, iron - ?), 
                 food = GREATEST(0, food - ?), 
                 stone = GREATEST(0, stone - ?) 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiii",
            $resources['wood'],
            $resources['oil'],
            $resources['iron'],
            $resources['food'],
            $resources['stone'],
            $defenderResourcesId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update defender resources: " . $stmt->error);
        }

        // Update attacker resources (add)
        $query = "UPDATE resources SET 
                 wood = wood + ?, 
                 oil = oil + ?, 
                 iron = iron + ?, 
                 food = food + ?, 
                 stone = stone + ? 
                 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iiiiii",
            $resources['wood'],
            $resources['oil'],
            $resources['iron'],
            $resources['food'],
            $resources['stone'],
            $attackerResourcesId
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to update attacker resources: " . $stmt->error);
        }
    }

    /**
     * Get resources ID for an entity
     * 
     * @param int $id Entity ID
     * @param string $type Entity type
     * @return int|null Resources ID or null if not found
     */
    private function getResourcesId($id, $type)
    {
        if ($type === 'player') {
            $query = "SELECT resources_id FROM cities WHERE player_id = ? LIMIT 1";
        } elseif ($type === 'ai') {
            $query = "SELECT resources_id FROM ai_cities WHERE id = ?";
        } else {
            return null;
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['resources_id'];
        } else {
            return null;
        }
    }

    /**
     * Get battle report
     * 
     * @param int $battleId Battle ID
     * @return string|null Battle report or null if not found
     */
    public function getBattleReport($battleId)
    {
        $query = "SELECT battle_report FROM battles WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $battleId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            return $row['battle_report'];
        } else {
            return null;
        }
    }

    /**
     * Get recent battles for a player
     * 
     * @param int $playerId Player ID
     * @param int $limit Maximum number of battles to return
     * @return array Recent battles
     */
    public function getRecentBattles($playerId, $limit = 10)
    {
        $query = "SELECT * FROM battles 
                 WHERE (attacker_id = ? AND attacker_type = 'player') 
                 OR (defender_id = ? AND defender_type = 'player') 
                 ORDER BY battle_date DESC LIMIT ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $playerId, $playerId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $battles = [];
        while ($row = $result->fetch_assoc()) {
            $battles[] = $row;
        }

        return $battles;
    }
    /**
     * Check if a string is valid JSON
     * 
     * @param string $json The JSON string to validate
     * @return bool True if valid JSON, false otherwise
     */
    private function isValidJson($json)
    {
        if (!is_string($json)) {
            return false;
        }

        // Check if it's just a raw integer "0" encoded as string - this is not valid for our purpose
        if ($json === "0") {
            return false;
        }

        json_decode($json);
        $isValidJson = json_last_error() === JSON_ERROR_NONE;

        // Additional check: ensure it's an object, not just a number
        if ($isValidJson) {
            $decoded = json_decode($json);
            if (!is_object($decoded) && !is_array($decoded)) {
                return false;
            }
        }

        return $isValidJson;
    }

    /**
     * Sanitize an array for JSON storage in MySQL
     * Ensures all values are properly typed for JSON encoding
     * 
     * @param mixed $data The data to sanitize
     * @return array The sanitized array
     */
    private function sanitizeArrayForJson($data)
    {
        // If not an array, create a simple default array
        if (!is_array($data)) {
            return array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skirmishers" => 0,
                "riders" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }

        // If empty array, provide defaults
        if (empty($data)) {
            return array(
                "fighters" => 0,
                "shooters" => 0,
                "vehicles" => 0,
                "skirmishers" => 0,
                "riders" => 0,
                "canons" => 0,
                "jets" => 0,
                "archers" => 0,
                "marauders" => 0
            );
        }

        $result = array();

        // Process each key/value pair
        foreach ($data as $key => $value) {
            // Sanitize the key - ensure it's a valid string key
            $sanitizedKey = is_string($key) ? preg_replace('/[^A-Za-z0-9_]/', '_', $key) : "item_$key";

            // Sanitize the value
            if (is_array($value)) {
                // Handle nested arrays (though we don't expect these in battle data)
                $result[$sanitizedKey] = $this->sanitizeArrayForJson($value);
            } elseif (is_numeric($value)) {
                // Convert all numbers to integers
                $result[$sanitizedKey] = (int)$value;
            } elseif (is_null($value)) {
                // Convert null to 0
                $result[$sanitizedKey] = 0;
            } elseif (is_bool($value)) {
                // Convert boolean to 0 or 1
                $result[$sanitizedKey] = $value ? 1 : 0;
            } elseif (is_string($value) && is_numeric($value)) {
                // Convert numeric strings to integers
                $result[$sanitizedKey] = (int)$value;
            } else {
                // Any other type, default to 0
                $result[$sanitizedKey] = 0;
            }
        }
        return $result;
    }

    /**
     * Initiate a battle with specific selected units
     * 
     * @param int $attackerId ID of the attacker
     * @param string $attackerType Type of attacker (player, ai, npc)
     * @param int $defenderId ID of the defender
     * @param string $defenderType Type of defender (player, ai, npc)
     * @param array $selectedUnits Selected units for battle
     * @param string $strategy Battle strategy
     * @return array Battle result
     */
    public function initiateBattleWithSelectedUnits($attackerId, $attackerType, $defenderId, $defenderType, $selectedUnits, $strategy = 'balanced')
    {
        try {
            // Validate parameters
            if (!$attackerId || !$attackerType || !$defenderId || !$defenderType) {
                throw new Exception("Invalid battle parameters provided");
            }

            // Validate selected units
            if (empty($selectedUnits) || !is_array($selectedUnits) || array_sum($selectedUnits) <= 0) {
                throw new Exception("No units selected for battle or invalid format for selected units.");
            }

            // Get attacker's full armies to validate selection
            $fullAttackerArmiesBeforeBattle = $this->getArmies($attackerId, $attackerType);

            // Validate that player has enough units
            foreach ($selectedUnits as $unitType => $count) {
                // Ensure count is numeric and positive
                if (!is_numeric($count) || $count < 0) {
                    throw new Exception("Invalid count for unit type {$unitType}.");
                }
                $count = intval($count); // Ensure integer
                if ($count > 0) {
                    $available = isset($fullAttackerArmiesBeforeBattle[$unitType]) ? intval($fullAttackerArmiesBeforeBattle[$unitType]) : 0;
                    if ($count > $available) {
                        throw new Exception("Not enough {$unitType} available. You have {$available} but tried to send {$count}.");
                    }
                }
            }

            // Create attacker armies from selected units for the battle simulation
            $attackerArmiesForSimulation = $this->createArmiesFromSelection($selectedUnits);
            $this->initializeUnitStats($attackerArmiesForSimulation);

            // Get defender armies
            $defenderArmies = $this->getArmies($defenderId, $defenderType);
            if (empty($defenderArmies) || $this->getTotalUnits($defenderArmies) <= 0) {
                throw new Exception("Defender has no units to battle with");
            }
            $this->initializeUnitStats($defenderArmies);

            // Initialize morale
            $this->attackerMorale = 100;
            $this->defenderMorale = 100;

            // Store initial armies for loss calculation within the simulation
            $initialAttackerArmiesForSim = $attackerArmiesForSimulation; // These are the selected units
            $initialDefenderArmies = $defenderArmies;

            $battleLog = [];
            $attackerOverallRouted = false; // Initialize $attackerOverallRouted
            $defenderOverallRouted = false; // Initialize $defenderOverallRouted

            // Start a database transaction
            $this->conn->autocommit(false);

            for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
                if ($this->getTotalUnits($attackerArmiesForSimulation) <= 0 || $this->getTotalUnits($defenderArmies) <= 0) {
                    break;
                }

                $roundResult = $this->simulateRound($attackerArmiesForSimulation, $defenderArmies, $strategy, $this->attackerMorale, $this->defenderMorale, $round);

                $attackerArmiesForSimulation = $roundResult['attacker_remaining_round'];
                $defenderArmies = $roundResult['defender_remaining_round'];

                $this->attackerMorale = $roundResult['attacker_morale_after_round'];
                $this->defenderMorale = $roundResult['defender_morale_after_round'];

                // Update overall rout status for selected units battle
                if ($roundResult['attacker_routed_this_round']) $attackerOverallRouted = true;
                if ($roundResult['defender_routed_this_round']) $defenderOverallRouted = true;

                $battleLogEntry = [
                    'round' => $round,
                    'attacker_losses_round' => $roundResult['attacker_losses_round'],
                    'defender_losses_round' => $roundResult['defender_losses_round'],
                    'attacker_morale' => $this->attackerMorale,
                    'defender_morale' => $this->defenderMorale,
                    'attacker_power_round' => $roundResult['attacker_power_round'],
                    'defender_power_round' => $roundResult['defender_power_round'],
                    'attacker_routed' => $roundResult['attacker_routed_this_round'],
                    'defender_routed' => $roundResult['defender_routed_this_round'],
                ];
                $battleLog[] = $battleLogEntry;

                if (($attackerOverallRouted && $defenderOverallRouted) ||
                    ($this->getTotalUnits($attackerArmiesForSimulation) <= 0 && $defenderOverallRouted) ||
                    ($this->getTotalUnits($defenderArmies) <= 0 && $attackerOverallRouted) ||
                    $this->attackerMorale <= 0 || $this->defenderMorale <= 0
                ) {
                    break;
                }
            }

            // Calculate total losses for the units that participated in the simulation
            $totalAttackerLossesInSim = $this->calculateTotalLosses($initialAttackerArmiesForSim, $attackerArmiesForSimulation);
            $totalDefenderLosses = $this->calculateTotalLosses($initialDefenderArmies, $defenderArmies);

            // Determine final battle result
            $finalResult = $this->determineFinalBattleResult($attackerArmiesForSimulation, $defenderArmies, $initialAttackerArmiesForSim, $initialDefenderArmies, $this->attackerMorale, $this->defenderMorale);

            // Calculate plundered resources
            $resourcesPlundered = [];
            if ($finalResult === 'attacker_victory') {
                $initialDefenderPower = $this->calculateArmyPower($initialDefenderArmies, true, null, 100); // Use initial defender state for plunder calc
                $initialAttackerPowerFromSelected = $this->calculateArmyPower($initialAttackerArmiesForSim, false, $strategy, 100); // Use selected units power
                $resourcesPlundered = $this->calculatePlunderedResources($initialDefenderPower, $initialAttackerPowerFromSelected);
            }

            $battleLogJson = json_encode($battleLog);
            if ($battleLogJson === false) {
                error_log("Failed to encode battle log for selected units battle: " . json_last_error_msg());
                $battleLogJson = '[{"error":"battle log encoding failed"}]';
            }

            // Record battle in database
            $battleId = $this->recordBattle(
                $attackerId,
                $attackerType,
                $defenderId,
                $defenderType,
                $finalResult,
                $totalAttackerLossesInSim, // These are losses from the selected units
                $totalDefenderLosses,
                $resourcesPlundered,
                $battleLogJson
            );

            // Update attacker armies by deducting the losses (totalAttackerLossesInSim) from their main army pool
            // The updatePlayerArmiesAfterSelectedBattle method needs to correctly subtract these losses.
            $this->updatePlayerArmiesAfterSelectedBattle($attackerId, $attackerType, $totalAttackerLossesInSim);

            // Update defender armies normally with their remaining units
            $this->updateArmiesAfterBattle($defenderId, $defenderType, $defenderArmies);

            // If attacker won, transfer resources
            if ($finalResult === 'attacker_victory') {
                $this->transferResources($attackerId, $attackerType, $defenderId, $defenderType, $resourcesPlundered);
            }

            // Commit the transaction
            $this->conn->commit();

            return [
                'battle_id' => $battleId,
                'result' => $finalResult,
                'attacker_losses' => $totalAttackerLossesInSim, // Losses of the selected units
                'defender_losses' => $totalDefenderLosses,
                'attacker_remaining_in_battle' => $attackerArmiesForSimulation, // Remaining of selected units
                'defender_remaining' => $defenderArmies,
                'resources_plundered' => $resourcesPlundered,
                'battle_log' => $battleLog,
                'attacker_morale_final' => $this->attackerMorale,
                'defender_morale_final' => $this->defenderMorale
            ];
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            try {
                $this->conn->rollback();
            } catch (Exception $rollbackEx) {
                error_log("Battle rollback error: " . $rollbackEx->getMessage());
            }

            // Log the error
            error_log("Battle error: " . $e->getMessage());

            // Rethrow it
            throw $e;
        }
    }

    /**
     * Create army array from selected units
     */
    private function createArmiesFromSelection($selectedUnits)
    {
        $armies = [
            'fighters' => 0,
            'shooters' => 0,
            'vehicles' => 0,
            'skirmishers' => 0,
            'riders' => 0,
            'canons' => 0,
            'jets' => 0,
            'archers' => 0,
            'marauders' => 0
        ];

        foreach ($selectedUnits as $unitType => $count) {
            if (isset($armies[$unitType]) && $count > 0) {
                $armies[$unitType] = intval($count);
            }
        }

        return $armies;
    }

    // The applyStrategyModifiers method is no longer needed as strategy is handled directly
    // in calculateArmyPower and calculateBattleResult.
    // private function applyStrategyModifiers($armies, $strategy) { ... }

    /**
     * Update player armies after a battle by subtracting the total losses incurred by the selected units.
     *
     * @param int $playerId The ID of the player.
     * @param string $playerType The type of the player (should be 'player').
     * @param array $totalLossesFromSim An array of total losses for each unit type from the simulation.
     */
    private function updatePlayerArmiesAfterSelectedBattle($playerId, $playerType, $totalLossesFromSim)
    {
        if ($playerType !== 'player') {
            // This method is specifically for players sending selected units.
            return;
        }

        foreach ($totalLossesFromSim as $unitType => $lostCount) {
            $lostCount = intval($lostCount);
            if ($lostCount > 0) {
                // We are deducting from the player's total available units of that type.
                // The $totalLossesFromSim already represents the actual number of units lost from the contingent sent.
                $query = "UPDATE player_armies SET {$unitType} = GREATEST(0, {$unitType} - ?) WHERE player_id = ?";
                $stmt = $this->conn->prepare($query);
                if (!$stmt) {
                    error_log("Prepare failed for updatePlayerArmiesAfterSelectedBattle ({$unitType}): " . $this->conn->error);
                    throw new Exception("Database prepare error updating player armies for {$unitType}.");
                }
                $stmt->bind_param("ii", $lostCount, $playerId);
                if (!$stmt->execute()) {
                    error_log("Execute failed for updatePlayerArmiesAfterSelectedBattle ({$unitType}): " . $stmt->error);
                    throw new Exception("Failed to update player armies for {$unitType}: " . $stmt->error);
                }
            }
        }
    }
}
