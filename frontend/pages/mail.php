<?php
/**
 * Mail System Page - Complete messaging interface for MechaEmpire
 * Features: Inbox, Compose, Message Viewing, Search, Filters
 */

include_once __DIR__ . '/../templates/header.php';
include_once __DIR__ . '/../templates/topnav.php';

// Use globals system
$g = globals();

// Require authentication
if (!$g->isUserLoggedIn()) {
    echo '<div class="container-fluid mt-5 pt-5">
            <div class="alert alert-warning text-center">
                <h4>Access Denied</h4>
                <p>You must be logged in to access the mail system.</p>
                <a href="index.php?page=login" class="btn btn-primary">Login</a>
            </div>
          </div>';
    include_once __DIR__ . '/../templates/footer.php';
    exit;
}

$user_id = $g->getCurrentUser('id');
$username = $g->getCurrentUser('uname');

// Include required resources
echo '<link rel="stylesheet" href="frontend/design/css/mail.css">';
echo '<script src="frontend/design/js/mail.js" defer></script>';
?>

<div class="container-fluid mt-5 pt-3">
    <div class="row">
        <!-- Mail Navigation Sidebar -->
        <div class="col-md-3">
            <div class="card mail-sidebar">
                <div class="card-body">
                    <button class="btn btn-primary btn-block mb-3" id="compose-btn">
                        <i class="fas fa-plus"></i> Compose Message
                    </button>
                    
                    <div class="list-group mail-nav">
                        <a href="#" class="list-group-item list-group-item-action active" data-filter="all">
                            <i class="fas fa-inbox"></i> Inbox
                            <span class="badge badge-primary badge-pill float-right" id="unread-count">0</span>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-filter="unread">
                            <i class="fas fa-envelope"></i> Unread
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-filter="starred">
                            <i class="fas fa-star"></i> Starred
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-filter="system">
                            <i class="fas fa-cog"></i> System Messages
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" data-filter="personal">
                            <i class="fas fa-users"></i> Personal Messages
                        </a>
                    </div>
                    
                    <hr>
                    
                    <!-- Quick Stats -->
                    <div class="mail-stats">
                        <h6 class="text-muted">Mail Statistics</h6>
                        <div class="stat-item">
                            <small class="text-muted">Total Messages:</small>
                            <span id="total-count">0</span>
                        </div>
                        <div class="stat-item">
                            <small class="text-muted">Unread Messages:</small>
                            <span id="unread-count-detail">0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Mail Content -->
        <div class="col-md-9">
            <!-- Mail Toolbar -->
            <div class="card mb-3">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="refresh-btn">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="mark-read-btn">
                                    <i class="fas fa-envelope-open"></i> Mark Read
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="delete-selected-btn">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="search-messages" placeholder="Search messages...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Messages List -->
            <div class="card" id="messages-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span id="current-view-title">Inbox</span>
                        <small class="text-muted" id="message-count-info">(Loading...)</small>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <!-- Loading Spinner -->
                    <div id="loading-spinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                    
                    <!-- Messages Container -->
                    <div id="messages-container" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover message-table mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="select-all-messages">
                                        </th>
                                        <th width="40"></th> <!-- Star column -->
                                        <th>From</th>
                                        <th>Subject</th>
                                        <th width="120">Date</th>
                                        <th width="60">Priority</th>
                                        <th width="40"></th> <!-- Actions -->
                                    </tr>
                                </thead>
                                <tbody id="messages-tbody">
                                    <!-- Messages will be populated here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div id="empty-state" class="text-center py-5" style="display: none;">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No messages found</h5>
                            <p class="text-muted">Your inbox is empty or no messages match your current filter.</p>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="card-footer" id="pagination-container" style="display: none;">
                        <nav aria-label="Messages pagination">
                            <ul class="pagination pagination-sm justify-content-center mb-0" id="pagination">
                                <!-- Pagination will be populated here -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Message Modal -->
<div class="modal fade" id="composeModal" tabindex="-1" role="dialog" aria-labelledby="composeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="composeModalLabel">
                    <i class="fas fa-edit"></i> Compose Message
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="compose-form">
                    <div class="form-group">
                        <label for="message-recipients">To:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="message-recipients" 
                                   placeholder="Type username to search..." autocomplete="off">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="search-users-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div id="recipients-suggestions" class="suggestions-dropdown"></div>
                        <div id="selected-recipients" class="selected-recipients mt-2"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message-subject">Subject:</label>
                        <input type="text" class="form-control" id="message-subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message-priority">Priority:</label>
                        <select class="form-control" id="message-priority">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message-content">Message:</label>
                        <textarea class="form-control" id="message-content" rows="8" required></textarea>
                    </div>
                    
                    <!-- Future: Attachments section -->
                    <div class="form-group" style="display: none;">
                        <label>Attachments:</label>
                        <div id="message-attachments">
                            <p class="text-muted">Attachments feature coming soon!</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="send-message-btn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Message Modal -->
<div class="modal fade" id="viewMessageModal" tabindex="-1" role="dialog" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMessageModalLabel">
                    <i class="fas fa-envelope-open"></i> Message Details
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="message-details">
                <!-- Message content will be populated here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="star-message-btn">
                    <i class="fas fa-star"></i> Star
                </button>
                <button type="button" class="btn btn-outline-primary" id="reply-message-btn">
                    <i class="fas fa-reply"></i> Reply
                </button>
                <button type="button" class="btn btn-outline-danger" id="delete-message-btn">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-content">
        <div class="spinner-border text-light" role="status">
            <span class="sr-only">Loading...</span>
        </div>
        <p class="mt-2 text-light">Processing...</p>
    </div>
</div>

<!-- Initialize JavaScript -->
<script>
// Pass PHP variables to JavaScript
window.mailConfig = {
    userId: <?php echo $user_id; ?>,
    username: '<?php echo htmlspecialchars($username); ?>',
    apiBase: 'backend/scripts/'
};
</script>

<?php include_once __DIR__ . '/../templates/footer.php'; ?>