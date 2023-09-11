$(document).ready(function () {
    // Format the numbers with the appropriate suffix
    function formatNumber(num) {
        if (num.gte(1e18)) {
            return `${num.div(1e18).toFixed(2)} qt`;
        } else if (num.gte(1e15)) {
            return `${num.div(1e15).toFixed(2)} qd`;
        } else if (num.gte(1e12)) {
            return `${num.div(1e12).toFixed(2)} tr`;
        } else if (num.gte(1e9)) {
            return `${num.div(1e9).toFixed(2)} b`;
        } else if (num.gte(1e6)) {
            return `${num.div(1e6).toFixed(2)} m`;
        } else if (num.gte(1e3)) {
            return `${num.div(1e3).toFixed(2)} k`;
        } else {
            return num.toString();
        }
    }

    // Format the numbers with the appropriate suffix
    const values = Array.from(document.querySelectorAll('.cr-value')).map(value => {
        value.innerText = formatNumber(new Big(value.innerText));
        return value;
    });

    const timerEl = document.querySelector('#vm-skill-timer .vm-skill-time');
    const timerBarEl = document.querySelector('#vm-skill-timer .skill-timer');
    const queueTimerEl = document.querySelector('#vm-queue-timer .vm-queue-time');
    const queueBarEl = document.querySelector('#vm-queue-timer .queue-timer');
    const queueCheckEl = document.querySelector('#vm-queue-timer .vm-queue-check p');

    let skillTime = 10000; // Skill times in seconds
    let queueTime = 120; // Queue times in seconds
    let completedQueues = 0; // Number of completed queues
    let totalQueueTime = queueTime; // Total time for the current queue in seconds
    const maxQueueTime = queueTime;
    const maxTime = skillTime;

    // Define a function to format the time
    function formatTime(time) {
        const days = Math.floor(time / (60 * 60 * 24));
        const hours = Math.floor((time % (60 * 60 * 24)) / (60 * 60));
        const minutes = Math.floor((time % (60 * 60)) / 60);
        const seconds = time % 60;

        if (days > 0) {
            return `${days}d:${hours}h:${minutes}m`;
        } else if (hours > 0) {
            return `${hours}h:${minutes}m:${seconds}s`;
        } else if (minutes > 0) {
            return `${minutes}m:${seconds}s`;
        } else {
            return `${seconds}s`;
        }
    }


    // Define a function to update the timer display and bar
    function updateTimer(el, barEl, timeLeft, maxTime) {
        const formattedTime = formatTime(timeLeft);
        el.textContent = formattedTime;
        const timePercentage = (timeLeft / maxTime) * 100;
        barEl.style.transform = `scaleX(${timePercentage / 100})`;
        if (timeLeft < 60) {
            const seconds = timeLeft % 60;
            el.textContent = `${seconds}s`;
        }
    }

    // Define a function to update the number of completed training queues
    function updateQueueCheck(num) {
        queueCheckEl.textContent = num.toString();
    }

    // Define a function to update the timer for the current queue time
    function updateQueueTimer() {
        updateTimer(queueTimerEl, queueBarEl, totalQueueTime, maxQueueTime);
        totalQueueTime--;
        if (totalQueueTime < 0) {
            completedQueues++;
            updateQueueCheck(completedQueues);
            clearInterval(queueInterval);
            queueTimerEl.textContent = 'No Queue';
            queueBarEl.style.transform = 'scaleX(0)';
            totalQueueTime = queueTime;
        }
    }

    // Call the updateTimer function every second for skill timer
    const skillTimerInterval = setInterval(() => {
        skillTime--;
        updateTimer(timerEl, timerBarEl, skillTime, maxTime);
        if (skillTime < 0) {
            clearInterval(skillTimerInterval);
            timerEl.textContent = 'Ready!';
            timerBarEl.style.transform = 'scaleX(0)';
        }
    }, 1000);

    // Call the updateQueueTimer function every second for queue timer
    const queueInterval = setInterval(updateQueueTimer, 1000);

    const lockedSVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255, 255, 255, 0.5)" class="w-6 h-6"><path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" /></svg>';

    const lockedHTML = `<div>${lockedSVG}</div>`;

    const locked = lockedHTML;

    // Set the initial player level and data
    let playerLevel = 12;
    let data = {
        tech: 100,
        maxtech: 200,
        heroes: 50,
        totalHeroes: 100,
        clan: 75,
        clanpopulation: 150,
        forge: 25,
        totalforge: 50,
        quest: 15,
        totalquest: 30,
    };

    // Minimum levels required to unlock each circle
    const minLevels = [0, 10, 20, 30, 40];

    // Get the circles and texts
    const circles = document.querySelectorAll('.manage-circle circle:nth-of-type(2)');
    const texts = document.querySelectorAll('.manage-circle .manage-text');

    // Define a function to update the progress circles
    function updateProgress() {
        circles.forEach((circle, index) => {
            const circumference = 2 * Math.PI * circle.getAttribute('r');

            circle.style.strokeDasharray = circumference;

            function setProgress(percent) {
                const offset = circumference - (percent / 100) * circumference;
                circle.style.strokeDashoffset = offset;
                texts[index].textContent = `${percent.toFixed(1)}%`;
                texts[index].style.fontSize = '12px';
            }

            texts[index].style.transform = '';
            if (playerLevel >= minLevels[index]) {
                if (index === 0) {
                    setProgress((data.tech / data.maxtech) * 100);
                } else if (index === 1) {
                    setProgress((data.heroes / data.totalHeroes) * 100);
                } else if (index === 2) {
                    setProgress((data.clan / data.clanpopulation) * 100);
                } else if (index === 3) {
                    setProgress((data.forge / data.totalforge) * 100);
                } else if (index === 4) {
                    setProgress((data.quest / data.totalquest) * 100);
                }
            } else {
                circle.style.strokeDashoffset = circumference;
                texts[index].innerHTML = locked;
                texts[index].style.transform = 'rotate(-90deg)';
                texts[index].style.fontSize = '10px';
            }
        });
    }

    // Call the updateProgress() function initially
    updateProgress();

    // Set the time interval to check for updates (in milliseconds)
    const intervalTime = 5000;

    // Call the updateProgress() function periodically using setInterval()
    setInterval(() => {
        // Update the player level and data here
        playerLevel = 25;
        data.tech = 150;
        data.heroes = 60;
        data.clan = 85;
        data.forge = 30;
        data.quest = 20;

        // Call the updateProgress() function to update the circles and texts
        updateProgress();
    }, intervalTime);


});