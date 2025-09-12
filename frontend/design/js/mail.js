/**
 * Mail System JavaScript - Frontend functionality for MechaEmpire mail system
 * Handles AJAX operations, UI interactions, and real-time updates
 */

class MailSystem {
    constructor() {
        this.currentFilter = 'all';
        this.currentPage = 1;
        this.selectedMessages = new Set();
        this.selectedRecipients = new Set();
        this.searchTimeout = null;
        this.recipientSearchTimeout = null;
        this.refreshInterval = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadMessages();
        this.startRefreshTimer();
        this.updateUnreadCount();
    }
    
    bindEvents() {
        // Navigation events
        document.querySelectorAll('.mail-nav a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.changeFilter(e.target.closest('a'));
            });
        });
        
        // Toolbar events
        document.getElementById('compose-btn').addEventListener('click', () => this.openComposeModal());
        document.getElementById('refresh-btn').addEventListener('click', () => this.loadMessages());
        document.getElementById('mark-read-btn').addEventListener('click', () => this.markSelectedAsRead());
        document.getElementById('delete-selected-btn').addEventListener('click', () => this.deleteSelected());
        
        // Search events
        const searchInput = document.getElementById('search-messages');
        searchInput.addEventListener('input', (e) => this.debounceSearch(e.target.value));
        document.getElementById('search-btn').addEventListener('click', () => this.searchMessages());
        
        // Select all checkbox
        document.getElementById('select-all-messages').addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });
        
        // Compose modal events
        document.getElementById('send-message-btn').addEventListener('click', () => this.sendMessage());
        document.getElementById('message-recipients').addEventListener('input', (e) => {
            this.debounceRecipientSearch(e.target.value);
        });
        document.getElementById('message-recipients').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.handleDirectRecipientAdd(e.target.value.trim());
            }
        });
        
        // View message modal events
        document.getElementById('star-message-btn').addEventListener('click', () => this.toggleStarMessage());
        document.getElementById('reply-message-btn').addEventListener('click', () => this.replyToMessage());
        document.getElementById('delete-message-btn').addEventListener('click', () => this.deleteCurrentMessage());
        
        // Modal cleanup events
        $('#composeModal').on('hidden.bs.modal', () => this.clearComposeForm());
        $('#viewMessageModal').on('hidden.bs.modal', () => this.clearMessageView());
        
        // Auto-refresh when window gets focus
        window.addEventListener('focus', () => {
            this.loadMessages();
            this.updateUnreadCount();
        });
    }
    
    // Filter and Navigation
    changeFilter(link) {
        // Update active nav item
        document.querySelectorAll('.mail-nav a').forEach(a => a.classList.remove('active'));
        link.classList.add('active');
        
        // Get filter and update
        this.currentFilter = link.dataset.filter;
        this.currentPage = 1;
        this.clearSelection();
        
        // Update title
        const title = link.textContent.trim();
        document.getElementById('current-view-title').textContent = title;
        
        this.loadMessages();
    }
    
    // Message Loading
    async loadMessages(page = 1) {
        this.currentPage = page;
        this.showLoading();
        
        try {
            const searchQuery = document.getElementById('search-messages').value.trim();
            
            const params = new URLSearchParams({
                page: this.currentPage,
                filter: this.currentFilter,
                limit: 20
            });
            
            if (searchQuery) {
                params.append('search', searchQuery);
            }
            
            const response = await fetch(`${window.mailConfig.apiBase}get_messages.php?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderMessages(data.messages);
                this.renderPagination(data.pagination);
                this.updateMessageCount(data.pagination);
                this.updateUnreadCount(data.unread_count);
            } else {
                this.showError('Failed to load messages: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            this.showError('Network error occurred');
        }
        
        this.hideLoading();
    }
    
    renderMessages(messages) {
        const tbody = document.getElementById('messages-tbody');
        const container = document.getElementById('messages-container');
        const emptyState = document.getElementById('empty-state');
        
        if (messages.length === 0) {
            container.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        container.style.display = 'block';
        emptyState.style.display = 'none';
        
        tbody.innerHTML = messages.map(message => `
            <tr class="message-row ${!message.is_read ? 'unread' : ''}" data-message-id="${message.id}">
                <td>
                    <input type="checkbox" class="message-checkbox" value="${message.id}">
                </td>
                <td>
                    <i class="fas fa-star ${message.is_starred ? 'text-warning' : 'text-muted'}" 
                       title="${message.is_starred ? 'Starred' : 'Not starred'}"></i>
                </td>
                <td>
                    <div class="sender-info">
                        <span class="sender-name">${this.escapeHtml(message.sender_name)}</span>
                        <small class="sender-type text-muted">(${message.sender_type})</small>
                    </div>
                </td>
                <td>
                    <div class="subject-container">
                        <span class="message-subject" onclick="mailSystem.viewMessage(${message.id})" 
                              style="cursor: pointer;">
                            ${this.escapeHtml(message.subject)}
                        </span>
                        ${message.has_attachments ? '<i class="fas fa-paperclip text-muted ml-1" title="Has attachments"></i>' : ''}
                        ${message.reply_to_id ? '<i class="fas fa-reply text-muted ml-1" title="Reply"></i>' : ''}
                    </div>
                    <small class="message-preview text-muted">
                        ${this.truncateText(this.stripHtml(message.content), 100)}
                    </small>
                </td>
                <td>
                    <small class="text-muted" title="${message.created_at}">
                        ${message.time_ago}
                    </small>
                </td>
                <td>
                    <span class="badge badge-${this.getPriorityClass(message.priority)}">
                        ${message.priority}
                    </span>
                </td>
                <td>
                    <div class="btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm" 
                                onclick="mailSystem.viewMessage(${message.id})" 
                                title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
        
        // Bind checkbox events
        tbody.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateSelection());
        });
    }
    
    renderPagination(pagination) {
        const container = document.getElementById('pagination-container');
        const paginationEl = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'block';
        
        let paginationHtml = '';
        
        // Previous button
        if (pagination.page > 1) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="mailSystem.loadMessages(${pagination.page - 1})">
                        Previous
                    </a>
                </li>`;
        }
        
        // Page numbers
        const start = Math.max(1, pagination.page - 2);
        const end = Math.min(pagination.total_pages, pagination.page + 2);
        
        for (let i = start; i <= end; i++) {
            paginationHtml += `
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="mailSystem.loadMessages(${i})">${i}</a>
                </li>`;
        }
        
        // Next button
        if (pagination.page < pagination.total_pages) {
            paginationHtml += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="mailSystem.loadMessages(${pagination.page + 1})">
                        Next
                    </a>
                </li>`;
        }
        
        paginationEl.innerHTML = paginationHtml;
    }
    
    // Message Viewing
    async viewMessage(messageId) {
        try {
            const response = await fetch(`${window.mailConfig.apiBase}get_message.php?id=${messageId}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderMessageModal(data.message);
                $('#viewMessageModal').modal('show');
                
                // Update message as read in the list
                this.markMessageAsRead(messageId);
            } else {
                this.showError('Failed to load message: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading message:', error);
            this.showError('Network error occurred');
        }
    }
    
    renderMessageModal(message) {
        const details = document.getElementById('message-details');
        const starBtn = document.getElementById('star-message-btn');
        const replyBtn = document.getElementById('reply-message-btn');
        const deleteBtn = document.getElementById('delete-message-btn');
        
        // Store current message ID for actions
        this.currentMessageId = message.id;
        
        // Update star button
        starBtn.innerHTML = `<i class="fas fa-star"></i> ${message.is_starred ? 'Unstar' : 'Star'}`;
        
        // Render message content
        details.innerHTML = `
            <div class="message-header mb-3">
                <div class="row">
                    <div class="col-md-8">
                        <h5>${this.escapeHtml(message.subject)}</h5>
                        <p class="mb-1">
                            <strong>From:</strong> ${this.escapeHtml(message.sender_name)} 
                            <small class="text-muted">(${message.sender_type})</small>
                        </p>
                        <p class="mb-1">
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> ${message.created_at}
                                ${message.time_ago !== message.created_at ? `(${message.time_ago})` : ''}
                            </small>
                        </p>
                    </div>
                    <div class="col-md-4 text-right">
                        <span class="badge badge-${this.getPriorityClass(message.priority)} mb-2">
                            ${message.priority} priority
                        </span>
                        ${message.is_starred ? '<br><i class="fas fa-star text-warning" title="Starred"></i>' : ''}
                        ${message.has_attachments ? '<br><i class="fas fa-paperclip text-muted" title="Has attachments"></i>' : ''}
                    </div>
                </div>
            </div>
            
            <div class="message-content">
                <div class="card">
                    <div class="card-body">
                        ${this.formatMessageContent(message.content)}
                    </div>
                </div>
            </div>
            
            ${message.has_attachments && message.attachments.length > 0 ? `
                <div class="message-attachments mt-3">
                    <h6>Attachments:</h6>
                    <div class="list-group">
                        ${message.attachments.map(attachment => `
                            <div class="list-group-item">
                                <i class="fas fa-${this.getAttachmentIcon(attachment.type)}"></i>
                                ${attachment.name}
                                ${attachment.quantity > 1 ? `<span class="badge badge-secondary">${attachment.quantity}</span>` : ''}
                                ${attachment.is_claimed ? '<span class="badge badge-success">Claimed</span>' : 
                                  '<button class="btn btn-sm btn-primary float-right">Claim</button>'}
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        `;
        
        // Hide reply button for system messages
        replyBtn.style.display = message.sender_type === 'player' ? 'inline-block' : 'none';
    }
    
    // Compose and Send Messages
    openComposeModal(replyTo = null) {
        const modal = $('#composeModal');
        const title = document.getElementById('composeModalLabel');
        
        if (replyTo) {
            title.innerHTML = '<i class="fas fa-reply"></i> Reply to Message';
            document.getElementById('message-subject').value = 'Re: ' + replyTo.subject;
            document.getElementById('message-content').value = `\n\n--- Original Message ---\nFrom: ${replyTo.sender_name}\nDate: ${replyTo.created_at}\nSubject: ${replyTo.subject}\n\n${replyTo.content}`;
            
            // Set reply recipient
            this.selectedRecipients.clear();
            this.selectedRecipients.add({
                id: replyTo.sender_id,
                username: replyTo.sender_name
            });
            this.updateRecipientsDisplay();
        } else {
            title.innerHTML = '<i class="fas fa-edit"></i> Compose Message';
        }
        
        modal.modal('show');
    }
    
    async sendMessage() {
        const recipients = Array.from(this.selectedRecipients);
        const subject = document.getElementById('message-subject').value.trim();
        const content = document.getElementById('message-content').value.trim();
        const priority = document.getElementById('message-priority').value;
        
        // Validation
        if (recipients.length === 0) {
            this.showError('Please select at least one recipient');
            return;
        }
        
        if (!subject) {
            this.showError('Please enter a subject');
            return;
        }
        
        if (!content) {
            this.showError('Please enter a message');
            return;
        }
        
        this.showLoading('Sending message...');
        
        try {
            const payload = {
                recipients: recipients.map(r => r.username || r.id),
                subject: subject,
                content: content,
                priority: priority
            };
            
            const response = await fetch(`${window.mailConfig.apiBase}send_message.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`Message sent successfully to ${data.recipients_count} recipient(s)!`);
                $('#composeModal').modal('hide');
                this.clearComposeForm();
            } else {
                this.showError('Failed to send message: ' + (data.error || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error sending message:', error);
            this.showError('Network error occurred');
        }
        
        this.hideLoading();
    }
    
    // User Search for Recipients - with debouncing
    debounceRecipientSearch(query) {
        clearTimeout(this.recipientSearchTimeout);
        this.recipientSearchTimeout = setTimeout(() => {
            this.searchUsers(query);
        }, 300);
    }
    
    // Handle direct recipient addition (when user types exact username)
    async handleDirectRecipientAdd(query) {
        if (!query || query.length < 2) return;
        
        // First check if it's already selected
        const alreadySelected = Array.from(this.selectedRecipients).some(r => 
            r.username.toLowerCase() === query.toLowerCase()
        );
        
        if (alreadySelected) {
            this.showError('User already selected');
            return;
        }
        
        // Try to find exact match
        try {
            const response = await fetch(`${window.mailConfig.apiBase}search_users.php?q=${encodeURIComponent(query)}&limit=10`);
            const data = await response.json();
            
            if (data.success && data.users.length > 0) {
                // Look for exact match first
                const exactMatch = data.users.find(u => u.username.toLowerCase() === query.toLowerCase());
                if (exactMatch) {
                    this.addRecipient(exactMatch);
                    document.getElementById('message-recipients').value = '';
                    this.hideSuggestions();
                    return;
                }
                
                // If no exact match, show suggestions
                this.showSuggestions(data.users);
            } else {
                this.showError('User not found: ' + query);
            }
        } catch (error) {
            console.error('Error searching for user:', error);
            this.showError('Error searching for user');
        }
    }
    
    async searchUsers(query) {
        if (!query || query.length < 2) {
            this.hideSuggestions();
            return;
        }
        
        try {
            const response = await fetch(`${window.mailConfig.apiBase}search_users.php?q=${encodeURIComponent(query)}&limit=10`);
            const data = await response.json();
            
            if (data.success && data.users.length > 0) {
                this.showSuggestions(data.users);
            } else {
                this.hideSuggestions();
            }
        } catch (error) {
            console.error('Error searching users:', error);
            this.hideSuggestions();
        }
    }
    
    showSuggestions(users) {
        const container = document.getElementById('recipients-suggestions');
        
        container.innerHTML = users.map(user => `
            <div class="suggestion-item" data-user-id="${user.id}" data-username="${user.username}">
                <i class="fas fa-user"></i> ${this.escapeHtml(user.username)}
            </div>
        `).join('');
        
        container.style.display = 'block';
        
        // Bind click events
        container.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.addRecipient({
                    id: parseInt(item.dataset.userId),
                    username: item.dataset.username
                });
                this.hideSuggestions();
                document.getElementById('message-recipients').value = '';
            });
        });
    }
    
    hideSuggestions() {
        document.getElementById('recipients-suggestions').style.display = 'none';
    }
    
    addRecipient(user) {
        // Avoid duplicates
        const exists = Array.from(this.selectedRecipients).some(r => r.id === user.id);
        if (!exists) {
            this.selectedRecipients.add(user);
            this.updateRecipientsDisplay();
        }
    }
    
    updateRecipientsDisplay() {
        const container = document.getElementById('selected-recipients');
        const recipients = Array.from(this.selectedRecipients);
        
        container.innerHTML = recipients.map(recipient => `
            <span class="badge badge-primary recipient-badge" data-user-id="${recipient.id}">
                ${this.escapeHtml(recipient.username)}
                <button type="button" class="close ml-1" onclick="mailSystem.removeRecipient(${recipient.id})">
                    <span>&times;</span>
                </button>
            </span>
        `).join('');
    }
    
    removeRecipient(userId) {
        this.selectedRecipients = new Set(
            Array.from(this.selectedRecipients).filter(r => r.id !== userId)
        );
        this.updateRecipientsDisplay();
    }
    
    // Message Actions
    async toggleStarMessage() {
        if (!this.currentMessageId) return;
        
        try {
            const response = await fetch(`${window.mailConfig.apiBase}mail_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'toggle_star',
                    message_id: this.currentMessageId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const starBtn = document.getElementById('star-message-btn');
                starBtn.innerHTML = `<i class="fas fa-star"></i> ${data.starred ? 'Unstar' : 'Star'}`;
                this.loadMessages(this.currentPage); // Refresh list
            }
        } catch (error) {
            console.error('Error toggling star:', error);
        }
    }
    
    async deleteCurrentMessage() {
        if (!this.currentMessageId) return;
        
        if (!confirm('Are you sure you want to delete this message?')) return;
        
        try {
            const response = await fetch(`${window.mailConfig.apiBase}mail_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    message_ids: [this.currentMessageId]
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess('Message deleted successfully');
                $('#viewMessageModal').modal('hide');
                this.loadMessages(this.currentPage); // Refresh list
            }
        } catch (error) {
            console.error('Error deleting message:', error);
        }
    }
    
    replyToMessage() {
        if (!this.currentMessageId) return;
        
        // Get current message details from modal
        const messageDetails = document.getElementById('message-details');
        const subject = messageDetails.querySelector('h5').textContent;
        const senderInfo = messageDetails.querySelector('.message-header p strong').nextSibling.textContent.trim();
        const content = messageDetails.querySelector('.message-content .card-body').textContent;
        
        const replyData = {
            id: this.currentMessageId,
            subject: subject,
            sender_name: senderInfo.split(' (')[0],
            sender_id: this.currentMessageId, // This would need to be stored properly
            created_at: new Date().toLocaleString(),
            content: content
        };
        
        $('#viewMessageModal').modal('hide');
        this.openComposeModal(replyData);
    }
    
    // Bulk Operations
    markSelectedAsRead() {
        const selected = this.getSelectedMessages();
        if (selected.length === 0) {
            this.showError('Please select messages to mark as read');
            return;
        }
        
        this.performBulkAction('mark_read', selected);
    }
    
    deleteSelected() {
        const selected = this.getSelectedMessages();
        if (selected.length === 0) {
            this.showError('Please select messages to delete');
            return;
        }
        
        if (!confirm(`Are you sure you want to delete ${selected.length} message(s)?`)) return;
        
        this.performBulkAction('delete', selected);
    }
    
    async performBulkAction(action, messageIds) {
        try {
            const response = await fetch(`${window.mailConfig.apiBase}mail_actions.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    message_ids: messageIds
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showSuccess(`${data.success_count} message(s) processed successfully`);
                this.clearSelection();
                this.loadMessages(this.currentPage);
            } else {
                this.showError('Failed to process messages');
            }
        } catch (error) {
            console.error('Error performing bulk action:', error);
            this.showError('Network error occurred');
        }
    }
    
    // Selection Management
    updateSelection() {
        const checkboxes = document.querySelectorAll('.message-checkbox');
        const checkedCount = document.querySelectorAll('.message-checkbox:checked').length;
        const selectAllCheckbox = document.getElementById('select-all-messages');
        
        // Update select-all checkbox state
        if (checkedCount === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCount === checkboxes.length) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
        
        // Update button states
        const hasSelection = checkedCount > 0;
        document.getElementById('mark-read-btn').disabled = !hasSelection;
        document.getElementById('delete-selected-btn').disabled = !hasSelection;
    }
    
    toggleSelectAll(checked) {
        document.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
        });
        this.updateSelection();
    }
    
    getSelectedMessages() {
        return Array.from(document.querySelectorAll('.message-checkbox:checked'))
            .map(checkbox => parseInt(checkbox.value));
    }
    
    clearSelection() {
        document.querySelectorAll('.message-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
        this.updateSelection();
    }
    
    markMessageAsRead(messageId) {
        const row = document.querySelector(`tr[data-message-id="${messageId}"]`);
        if (row) {
            row.classList.remove('unread');
        }
        this.updateUnreadCount();
    }
    
    // Utility Functions
    async updateUnreadCount(count = null) {
        if (count === null) {
            try {
                const response = await fetch(`${window.mailConfig.apiBase}mail_actions.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'get_unread_count'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    count = data.unread_count;
                }
            } catch (error) {
                console.error('Error getting unread count:', error);
                return;
            }
        }
        
        // Update unread count displays
        document.getElementById('unread-count').textContent = count || '0';
        document.getElementById('unread-count-detail').textContent = count || '0';
        
        // Update browser title if there are unread messages
        if (count > 0) {
            document.title = `(${count}) Mail - MechaEmpire`;
        } else {
            document.title = 'Mail - MechaEmpire';
        }
    }
    
    updateMessageCount(pagination) {
        const info = document.getElementById('message-count-info');
        const start = (pagination.page - 1) * pagination.limit + 1;
        const end = Math.min(pagination.page * pagination.limit, pagination.total);
        
        if (pagination.total === 0) {
            info.textContent = '(No messages)';
        } else {
            info.textContent = `(${start}-${end} of ${pagination.total})`;
        }
    }
    
    debounceSearch(query) {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            this.searchMessages(query);
        }, 500);
    }
    
    searchMessages(query = null) {
        if (query === null) {
            query = document.getElementById('search-messages').value.trim();
        }
        
        // Reset to first page and reload with search
        this.currentPage = 1;
        this.loadMessages();
    }
    
    startRefreshTimer() {
        // Refresh every 30 seconds
        this.refreshInterval = setInterval(() => {
            this.updateUnreadCount();
        }, 30000);
    }
    
    clearComposeForm() {
        document.getElementById('compose-form').reset();
        this.selectedRecipients.clear();
        this.updateRecipientsDisplay();
        this.hideSuggestions();
    }
    
    clearMessageView() {
        this.currentMessageId = null;
        document.getElementById('message-details').innerHTML = '';
    }
    
    showLoading(message = 'Loading...') {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.querySelector('p').textContent = message;
            overlay.style.display = 'flex';
        } else {
            document.getElementById('loading-spinner').style.display = 'block';
        }
    }
    
    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
        document.getElementById('loading-spinner').style.display = 'none';
    }
    
    showError(message) {
        // Use Bootstrap toast or alert
        this.showToast(message, 'danger');
    }
    
    showSuccess(message) {
        this.showToast(message, 'success');
    }
    
    showToast(message, type = 'info') {
        // Create a simple toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} toast-notification`;
        toast.innerHTML = `
            <strong>${type.charAt(0).toUpperCase() + type.slice(1)}:</strong> ${message}
            <button type="button" class="close" onclick="this.parentElement.remove()">
                <span>&times;</span>
            </button>
        `;
        
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.maxWidth = '400px';
        
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
    
    // Helper Functions
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }
    
    truncateText(text, length) {
        if (text.length <= length) return text;
        return text.substring(0, length) + '...';
    }
    
    formatMessageContent(content) {
        // Basic formatting - convert line breaks to HTML
        return content.replace(/\n/g, '<br>');
    }
    
    getPriorityClass(priority) {
        switch (priority) {
            case 'urgent': return 'danger';
            case 'high': return 'warning';
            case 'normal': return 'info';
            case 'low': return 'secondary';
            default: return 'info';
        }
    }
    
    getAttachmentIcon(type) {
        switch (type) {
            case 'resource': return 'coins';
            case 'item': return 'box';
            case 'unit': return 'shield-alt';
            case 'building_plan': return 'building';
            case 'map_data': return 'map';
            default: return 'file';
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.mailSystem = new MailSystem();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (window.mailSystem && window.mailSystem.refreshInterval) {
        clearInterval(window.mailSystem.refreshInterval);
    }
});