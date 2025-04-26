/**
 * KwetuPizza WhatsApp Inbox JavaScript
 * Handles all interactions with the WhatsApp chat interface
 */

(function($) {
    'use strict';

    // Global variables
    let currentPhone = '';
    let isTyping = false;
    let typingTimeout = null;
    let refreshInterval = null;
    let messageCache = {};

    // Initialize the chat functionality
    function initializeChat() {
        // Get current selected customer phone number
        const firstCustomer = $('.kwetupizza-customer-item.active');
        if (firstCustomer.length) {
            currentPhone = firstCustomer.data('phone');
            loadMessages(currentPhone);
        }

        // Set up event handlers
        setupEventHandlers();
        
        // Set up automatic refresh every 30 seconds
        refreshInterval = setInterval(function() {
            if (currentPhone) {
                loadMessages(currentPhone, true);
            }
        }, 30000);
    }

    // Set up all event handlers
    function setupEventHandlers() {
        // Customer selection
        $('.kwetupizza-customer-item').on('click', function() {
            const phone = $(this).data('phone');
            if (phone === currentPhone) return;
            
            // Update UI
            $('.kwetupizza-customer-item').removeClass('active');
            $(this).addClass('active');
            
            // Update header
            $('.kwetupizza-chat-username .customer-name').text($(this).find('.kwetupizza-customer-name').text().trim());
            $('.kwetupizza-chat-phone .customer-phone').text(phone);
            
            // Load new messages
            currentPhone = phone;
            loadMessages(phone);
        });

        // Search functionality
        $('.kwetupizza-search-input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            if (searchTerm === '') {
                $('.kwetupizza-customer-item').show();
                return;
            }
            
            $('.kwetupizza-customer-item').each(function() {
                const name = $(this).find('.kwetupizza-customer-name').text().toLowerCase();
                const phone = $(this).data('phone').toString();
                const message = $(this).find('.kwetupizza-customer-preview').text().toLowerCase();
                
                if (name.includes(searchTerm) || phone.includes(searchTerm) || message.includes(searchTerm)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Send message
        $('.kwetupizza-send-button').on('click', function() {
            sendMessage();
        });

        // Send on Enter key (but Shift+Enter for new line)
        $('.kwetupizza-message-textarea').on('keydown', function(e) {
            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }

            // Adjust textarea height
            setTimeout(() => {
                $(this).css('height', '40px');
                $(this).css('height', (this.scrollHeight > 40 ? this.scrollHeight : 40) + 'px');
            }, 0);
        });

        // Quick replies
        $('.kwetupizza-quick-reply').on('click', function() {
            const replyText = $(this).data('reply');
            $('.kwetupizza-message-textarea').val(replyText);
            sendMessage();
        });

        // Refresh button
        $('.kwetupizza-refresh-chat').on('click', function() {
            if (currentPhone) {
                loadMessages(currentPhone);
            }
        });

        // Order history button
        $('.kwetupizza-order-history').on('click', function() {
            if (currentPhone) {
                loadOrderHistory(currentPhone);
            }
        });

        // Handle window resize for responsive adjustments
        $(window).on('resize', function() {
            adjustChatHeight();
        });
    }

    // Load messages for a specific customer
    function loadMessages(phone, silent = false) {
        if (!phone) return;
        
        // Show loading if not silent refresh
        if (!silent) {
            $('.kwetupizza-chat-messages').html('<div class="kwetupizza-loading-messages"><div class="spinner is-active"></div><p>Loading messages...</p></div>');
        }
        
        // AJAX call to get messages
        $.ajax({
            url: kwetupizzaInbox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kwetupizza_get_user_messages',
                nonce: kwetupizzaInbox.nonce,
                phone_number: phone
            },
            success: function(response) {
                if (response.success && phone === currentPhone) {
                    // Check if we have new messages
                    const messagesChanged = hasMessagesChanged(phone, response.data);
                    
                    if (messagesChanged || !silent) {
                        // Store current messages for comparison later
                        messageCache[phone] = response.data;
                        
                        // Display messages
                        displayMessages(response.data);
                        
                        // Scroll to bottom
                        scrollToBottom();
                    }
                }
            },
            error: function() {
                if (!silent) {
                    $('.kwetupizza-chat-messages').html('<div class="kwetupizza-error-message">Error loading messages. Please try again.</div>');
                }
            }
        });
    }

    // Check if messages have changed
    function hasMessagesChanged(phone, newMessages) {
        if (!messageCache[phone] || !newMessages) return true;
        
        // If count doesn't match, they've changed
        if (messageCache[phone].length !== newMessages.length) return true;
        
        // Check the last message ID
        if (newMessages.length > 0 && messageCache[phone].length > 0) {
            const lastCachedId = messageCache[phone][messageCache[phone].length - 1].id;
            const lastNewId = newMessages[newMessages.length - 1].id;
            return lastCachedId !== lastNewId;
        }
        
        return false;
    }

    // Display messages in the chat area
    function displayMessages(messages) {
        if (!messages || messages.length === 0) {
            $('.kwetupizza-chat-messages').html('<div class="kwetupizza-no-messages">No messages yet. Start the conversation!</div>');
            return;
        }
        
        let output = '';
        let lastDate = '';
        
        // Group messages by date
        messages.forEach(function(message, index) {
            const messageDate = new Date(message.timestamp);
            const dateStr = messageDate.toLocaleDateString();
            
            // Add date separator if this is a new date
            if (dateStr !== lastDate) {
                lastDate = dateStr;
                output += `<div class="kwetupizza-day-separator">
                            <span class="kwetupizza-day-separator-text">${dateStr}</span>
                          </div>`;
            }
            
            // Format time
            const timeStr = messageDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            // Message direction
            const direction = message.direction === 'customer' ? 'customer' : 'admin';
            
            // Add message with animation delay based on position
            const animationDelay = index * 0.05;
            
            output += `<div class="kwetupizza-message kwetupizza-message-${direction}" style="animation-delay: ${animationDelay}s">
                        <div class="kwetupizza-message-bubble">
                            ${message.message.replace(/\n/g, '<br>')}
                        </div>
                        <span class="kwetupizza-message-time">${timeStr}</span>
                      </div>`;
        });
        
        $('.kwetupizza-chat-messages').html(output);
    }

    // Send a message
    function sendMessage() {
        const messageInput = $('.kwetupizza-message-textarea');
        const message = messageInput.val().trim();
        
        if (!message || !currentPhone) return;
        
        // Clear input
        messageInput.val('');
        messageInput.css('height', '40px');
        
        // Add message to UI immediately (optimistic UI)
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        $('.kwetupizza-chat-messages').append(`
            <div class="kwetupizza-message kwetupizza-message-admin">
                <div class="kwetupizza-message-bubble">
                    ${message.replace(/\n/g, '<br>')}
                </div>
                <span class="kwetupizza-message-time">${timeStr}</span>
            </div>
        `);
        
        // Scroll to bottom
        scrollToBottom();
        
        // Show typing indicator
        showTypingIndicator();
        
        // AJAX call to send message
        $.ajax({
            url: kwetupizzaInbox.ajaxUrl,
            type: 'POST',
            data: {
                action: 'kwetupizza_send_admin_message',
                nonce: kwetupizzaInbox.nonce,
                phone_number: currentPhone,
                message: message
            },
            success: function(response) {
                // Hide typing indicator
                hideTypingIndicator();
                
                if (!response.success) {
                    // Show error
                    showErrorNotification('Failed to send message');
                } else {
                    // Update customer preview with latest message
                    updateCustomerPreview(currentPhone, message);
                }
            },
            error: function() {
                // Hide typing indicator
                hideTypingIndicator();
                
                // Show error
                showErrorNotification('Error sending message');
            }
        });
    }

    // Update customer preview with latest message
    function updateCustomerPreview(phone, message) {
        const customerItem = $(`.kwetupizza-customer-item[data-phone="${phone}"]`);
        if (customerItem.length) {
            customerItem.find('.kwetupizza-customer-preview').text(message);
            customerItem.find('.kwetupizza-customer-time').text('just now');
            
            // Move to top of list
            const parentContainer = customerItem.parent();
            customerItem.detach();
            parentContainer.prepend(customerItem);
        }
    }

    // Load order history
    function loadOrderHistory(phone) {
        // Create modal
        let modal = $(`
            <div class="kwetupizza-order-history-popup">
                <div class="kwetupizza-order-history-content">
                    <div class="kwetupizza-order-history-header">
                        <h2>Order History - ${phone}</h2>
                        <button class="kwetupizza-order-history-close">&times;</button>
                    </div>
                    <div class="kwetupizza-order-history-body">
                        <div class="kwetupizza-loading-orders">
                            <div class="spinner is-active"></div>
                            <p>Loading order history...</p>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        // Add to document
        $('body').append(modal);
        
        // Handle close
        modal.find('.kwetupizza-order-history-close').on('click', function() {
            modal.remove();
        });
        
        // Close on outside click
        modal.on('click', function(e) {
            if ($(e.target).is(modal)) {
                modal.remove();
            }
        });
        
        // TODO: Load actual order history via AJAX
        // For now, we'll simulate it with dummy data
        setTimeout(function() {
            let orderHistoryHtml = '';
            
            // Dummy data
            const orderStatuses = ['pending', 'processing', 'completed', 'cancelled'];
            const statusLabels = {
                'pending': 'Pending',
                'processing': 'Processing',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };
            
            const productNames = ['Pepperoni Pizza', 'Margherita Pizza', 'Hawaiian Pizza', 'Veggie Pizza', 'Coca-Cola', 'Sprite'];
            
            for (let i = 1; i <= 5; i++) {
                const orderDate = new Date();
                orderDate.setDate(orderDate.getDate() - i);
                
                const status = orderStatuses[Math.floor(Math.random() * orderStatuses.length)];
                const orderTotal = (Math.floor(Math.random() * 50) + 10) * 1000; // Random price between 10,000 and 60,000
                
                // Generate random products
                let products = '';
                const numProducts = Math.floor(Math.random() * 3) + 1;
                
                for (let j = 0; j < numProducts; j++) {
                    const productName = productNames[Math.floor(Math.random() * productNames.length)];
                    const quantity = Math.floor(Math.random() * 3) + 1;
                    const price = (Math.floor(Math.random() * 15) + 5) * 1000;
                    
                    products += `<div class="kwetupizza-order-product">
                                    <div>${quantity} x ${productName}</div>
                                    <div>${formatCurrency(price)} TZS</div>
                                 </div>`;
                }
                
                orderHistoryHtml += `
                    <div class="kwetupizza-order-item">
                        <div class="kwetupizza-order-header">
                            <div class="kwetupizza-order-id">Order #${1000 + i}</div>
                            <div class="kwetupizza-order-date">${orderDate.toLocaleString()}</div>
                        </div>
                        <div class="kwetupizza-order-status kwetupizza-order-status-${status}">
                            ${statusLabels[status]}
                        </div>
                        <div class="kwetupizza-order-products">
                            ${products}
                        </div>
                        <div class="kwetupizza-order-total">
                            Total: ${formatCurrency(orderTotal)} TZS
                        </div>
                    </div>
                `;
            }
            
            if (orderHistoryHtml === '') {
                orderHistoryHtml = '<div class="kwetupizza-no-orders">No orders found for this customer.</div>';
            }
            
            modal.find('.kwetupizza-order-history-body').html(orderHistoryHtml);
        }, 1000);
    }

    // Show typing indicator
    function showTypingIndicator() {
        if (isTyping) return;
        
        isTyping = true;
        $('.kwetupizza-chat-messages').append(`
            <div class="kwetupizza-typing">
                <div class="kwetupizza-typing-indicator">
                    <div class="kwetupizza-typing-dot"></div>
                    <div class="kwetupizza-typing-dot"></div>
                    <div class="kwetupizza-typing-dot"></div>
                </div>
            </div>
        `);
        
        scrollToBottom();
        
        // Auto-hide after 3 seconds
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(hideTypingIndicator, 3000);
    }

    // Hide typing indicator
    function hideTypingIndicator() {
        $('.kwetupizza-typing').remove();
        isTyping = false;
    }

    // Show error notification
    function showErrorNotification(message) {
        // Create notification element
        const notification = $(`
            <div class="kwetupizza-notification kwetupizza-notification-error">
                <div class="kwetupizza-notification-message">${message}</div>
            </div>
        `);
        
        // Add to document
        $('body').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.addClass('visible');
        }, 10);
        
        // Auto-remove after 3 seconds
        setTimeout(function() {
            notification.removeClass('visible');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }

    // Format currency
    function formatCurrency(amount) {
        return new Intl.NumberFormat().format(amount);
    }

    // Scroll to bottom of chat
    function scrollToBottom() {
        const chatMessages = $('.kwetupizza-chat-messages');
        chatMessages.scrollTop(chatMessages[0].scrollHeight);
    }

    // Adjust chat height for responsive design
    function adjustChatHeight() {
        if (window.innerWidth <= 768) {
            const chatArea = $('.kwetupizza-chat-area');
            const customerList = $('.kwetupizza-customer-list');
            
            if (chatArea.is(':visible') && customerList.is(':visible')) {
                chatArea.height(window.innerHeight - customerList.height() - 100);
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initializeChat();
        adjustChatHeight();
        
        // Add some CSS for notifications that we didn't include in the main CSS file
        $('<style>')
            .text(`
                .kwetupizza-notification {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background-color: #fff;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    padding: 12px 20px;
                    z-index: 10000;
                    transform: translateY(20px);
                    opacity: 0;
                    transition: transform 0.3s, opacity 0.3s;
                }
                
                .kwetupizza-notification.visible {
                    transform: translateY(0);
                    opacity: 1;
                }
                
                .kwetupizza-notification-error {
                    border-left: 4px solid #e53e3e;
                }
                
                .kwetupizza-notification-success {
                    border-left: 4px solid #38a169;
                }
                
                .kwetupizza-notification-message {
                    font-size: 14px;
                    color: #4a5568;
                }
            `)
            .appendTo('head');
    });

    // Clean up when page is unloaded
    $(window).on('unload', function() {
        clearInterval(refreshInterval);
    });

})(jQuery); 