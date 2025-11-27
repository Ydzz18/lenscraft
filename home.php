<?php
session_start();

// Include database connection
require_once 'db_connect.php';

// Set page-specific variables
$page_title = 'Gallery';
$page_css = 'home.css';
$additional_css = 'view_photo.css';

// Function to get photos with like and comment counts
function getPhotos($pdo, $limit = 12) {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.title, 
            p.description, 
            p.image_path, 
            p.uploaded_at,
            u.username,
            u.id as user_id,
            COALESCE(l.like_count, 0) as like_count,
            COALESCE(c.comment_count, 0) as comment_count
        FROM photos p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN (
            SELECT photo_id, COUNT(*) as like_count 
            FROM likes 
            GROUP BY photo_id
        ) l ON p.id = l.photo_id
        LEFT JOIN (
            SELECT photo_id, COUNT(*) as comment_count 
            FROM comments 
            GROUP BY photo_id
        ) c ON p.id = c.photo_id
        ORDER BY p.uploaded_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get comments for a photo
function getPhotoComments($pdo, $photo_id, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.comment_text,
            c.created_at,
            u.username,
            u.id as user_id
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.photo_id = :photo_id
        ORDER BY c.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to check if user liked a photo
function userLikedPhoto($pdo, $photo_id, $user_id) {
    if (!$user_id) return false;
    
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE photo_id = :photo_id AND user_id = :user_id");
    $stmt->bindParam(':photo_id', $photo_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

// Get current user ID
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get photos from database
try {
    $pdo = getDBConnection();
    $photos = getPhotos($pdo);
} catch(PDOException $e) {
    $photos = [];
    error_log("Database error: " . $e->getMessage());
}

// Handle session messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages
unset($_SESSION['success']);
unset($_SESSION['error']);

// Include header
include 'header_logged_in.php';
?>

    <!-- Messages -->
    <?php if ($success_message): ?>
        <div class="messages">
            <div class="container">
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="messages">
            <div class="container">
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Capture. Share. Inspire.</h1>
            <p>Join the world's most passionate photography community. Upload your photos, connect with fellow photographers, and showcase your talent to the world.</p>
            <?php if ($user_id): ?>
                <a href="upload.php" class="btn btn-primary">Upload Your Photo</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary">Join Now</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="gallery-section">
        <div class="container">
            <h2 class="section-title">Latest Gallery</h2>
            <?php if (empty($photos)): ?>
                <div style="text-align: center; color: white; margin: 40px 0;">
                    <p>No photos uploaded yet. Be the first to share your photography!</p>
                </div>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($photos as $photo): ?>
                        <div class="photo-card" id="photo-<?php echo $photo['id']; ?>">
                            <div class="photo-image-container">
                                <img src="uploads/<?php echo htmlspecialchars($photo['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($photo['title']); ?>" 
                                     class="photo-image"
                                     onclick="openPhotoModal(<?php echo $photo['id']; ?>); return false;">
                            </div>
                            <div class="photo-info">
                                <h3 class="photo-title"><?php echo htmlspecialchars($photo['title']); ?></h3>
                                <div class="photo-meta">
                                    <span class="photo-user">By <?php echo htmlspecialchars($photo['username']); ?></span>
                                    <span><?php echo date('M j, Y', strtotime($photo['uploaded_at'])); ?></span>
                                </div>
                                <?php if (!empty($photo['description'])): ?>
                                    <p class="photo-description"><?php echo htmlspecialchars(substr($photo['description'], 0, 150)); ?><?php echo strlen($photo['description']) > 150 ? '...' : ''; ?></p>
                                <?php endif; ?>
                                <div class="photo-actions">
                                    <?php if ($user_id): ?>
                                        <a href="#" 
                                           class="like-btn <?php echo userLikedPhoto($pdo, $photo['id'], $user_id) ? 'liked' : ''; ?>"
                                           onclick="return likePhoto(<?php echo $photo['id']; ?>)">
                                            <i class="fas fa-heart"></i>
                                            <span><?php echo $photo['like_count']; ?></span>
                                        </a>
                                        <button type="button" class="comment-btn" onclick="openPhotoModal(<?php echo $photo['id']; ?>); return false;">
                                            <i class="fas fa-comment"></i>
                                            <span><?php echo $photo['comment_count']; ?></span>
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php" class="like-btn">
                                            <i class="fas fa-heart"></i>
                                            <span><?php echo $photo['like_count']; ?></span>
                                        </a>
                                        <a href="login.php" class="comment-btn">
                                            <i class="fas fa-comment"></i>
                                            <span><?php echo $photo['comment_count']; ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Photo Modal -->
    <div id="photoModal" class="photo-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Photo Details</h2>
                <button class="modal-close" onclick="closePhotoModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading">
                    <div class="spinner"></div>
                    <p class="loading-text">Loading photo...</p>
                </div>
            </div>
        </div>
    </div>

<?php
// Page-specific scripts
$page_scripts = <<<'SCRIPTS'
    <script>
        // Open Photo Modal
        function openPhotoModal(photoId) {
            const modal = document.getElementById('photoModal');
            const modalBody = document.getElementById('modalBody');
            
            // Show loading state
            modalBody.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p class="loading-text">Loading photo...</p>
                </div>
            `;
            
            modal.classList.add('active');
            
            // Fetch photo details
            fetch('view_photo.php?id=' + photoId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const photo = data.photo;
                    const comments = data.comments || [];
                    const userLiked = data.user_liked || false;
                    const isLoggedIn = data.is_logged_in || false;
                    
                    let commentsHTML = '';
                    if (comments.length > 0) {
                        commentsHTML = comments.map(comment => `
                            <div class="comment-item">
                                <div class="comment-header">
                                    <span class="comment-author">${escapeHtml(comment.username)}</span>
                                    <span class="comment-time">${formatDate(comment.created_at)}</span>
                                </div>
                                <div class="comment-text">${escapeHtml(comment.comment_text)}</div>
                            </div>
                        `).join('');
                    } else {
                        commentsHTML = '<div class="no-comments">No comments yet. Be the first to comment!</div>';
                    }
                    
                    const commentFormHTML = isLoggedIn ? `
                        <div class="comment-form-section">
                            <form onsubmit="submitModalComment(event, ${photoId})">
                                <div class="comment-input-group">
                                    <textarea class="comment-input" placeholder="Add a comment..." maxlength="500" required></textarea>
                                    <button type="submit" class="comment-submit">Post</button>
                                </div>
                            </form>
                        </div>
                    ` : `
                        <div class="comment-form-section">
                            <div class="login-prompt">
                                <a href="login.php">Login</a> to comment on this photo
                            </div>
                        </div>
                    `;
                    
                    const likeButtonHTML = isLoggedIn ? `
                        <button class="action-btn like-btn ${userLiked ? 'liked' : ''}" onclick="likePhotoFromModal(${photoId}, this)">
                            <i class="fas fa-heart"></i>
                            <span>${photo.like_count}</span>
                        </button>
                    ` : `
                        <a href="login.php" class="action-btn like-btn">
                            <i class="fas fa-heart"></i>
                            <span>${photo.like_count}</span>
                        </a>
                    `;
                    
                    const uploadDate = new Date(photo.uploaded_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    modalBody.innerHTML = `
                        <div class="photo-display">
                            <img src="uploads/${escapeHtml(photo.image_path)}" alt="${escapeHtml(photo.title)}" class="photo-main-image">
                            <div class="photo-stats">
                                <div class="stat">
                                    <i class="fas fa-heart"></i>
                                    <span>
                                        <span class="stat-value">${photo.like_count}</span>
                                        <span class="stat-label">Likes</span>
                                    </span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-comment"></i>
                                    <span>
                                        <span class="stat-value">${photo.comment_count}</span>
                                        <span class="stat-label">Comments</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="photo-details">
                            <div class="photo-info-section">
                                <div class="info-label">Title</div>
                                <div class="info-value">${escapeHtml(photo.title)}</div>
                            </div>
                            
                            <div class="photo-info-section">
                                <div class="info-label">Description</div>
                                <div class="info-value">${photo.description ? escapeHtml(photo.description) : '<em>No description provided</em>'}</div>
                            </div>
                            
                            <div class="photo-info-section">
                                <div class="info-label">Photographer</div>
                                <div class="photo-author">
                                    <div class="author-avatar">${photo.username.charAt(0).toUpperCase()}</div>
                                    <div class="author-info">
                                        <strong>${escapeHtml(photo.username)}</strong>
                                        <small>Posted on ${uploadDate}</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="photo-actions">
                                ${likeButtonHTML}
                            </div>
                            
                            <div class="comments-section">
                                <h3 class="comments-title">Comments</h3>
                                <div class="comments-list">
                                    ${commentsHTML}
                                </div>
                                ${commentFormHTML}
                            </div>
                        </div>
                    `;
                } else {
                    modalBody.innerHTML = `
                        <div class="error-state">
                            <div class="error-icon"><i class="fas fa-exclamation-circle"></i></div>
                            <div class="error-message">${data.message || 'Failed to load photo'}</div>
                            <a href="#" onclick="closePhotoModal(); return false;">Close Modal</a>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalBody.innerHTML = `
                    <div class="error-state">
                        <div class="error-icon"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="error-message">An error occurred while loading the photo</div>
                        <a href="#" onclick="closePhotoModal(); return false;">Close Modal</a>
                    </div>
                `;
            });
        }
        
        // Close Photo Modal
        function closePhotoModal() {
            const modal = document.getElementById('photoModal');
            modal.classList.remove('active');
        }
        
        // Like photo from modal
        function likePhotoFromModal(photoId, button) {
            fetch('like_ajax.php?id=' + photoId, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const likeCount = button.querySelector('span');
                    likeCount.textContent = data.like_count;
                    
                    if (data.liked) {
                        button.classList.add('liked');
                        const heart = button.querySelector('i');
                        heart.style.animation = 'heartBeat 0.3s ease';
                        setTimeout(() => heart.style.animation = '', 300);
                    } else {
                        button.classList.remove('liked');
                    }
                    
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message || 'Failed to like photo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
        }
        
        // Submit comment from modal
        function submitModalComment(event, photoId) {
            event.preventDefault();
            
            const form = event.target;
            const textarea = form.querySelector('.comment-input');
            const button = form.querySelector('.comment-submit');
            const comment = textarea.value;
            
            if (!comment.trim()) return;
            
            button.disabled = true;
            button.textContent = 'Posting...';
            
            const formData = new FormData();
            formData.append('photo_id', photoId);
            formData.append('comment', comment);
            
            fetch('comment_ajax.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textarea.value = '';
                    showMessage(data.message, 'success');
                    // Reload modal to show new comment
                    openPhotoModal(photoId);
                } else {
                    showMessage(data.message || 'Failed to post comment', 'error');
                }
                
                button.disabled = false;
                button.textContent = 'Post';
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                button.disabled = false;
                button.textContent = 'Post';
            });
        }
        
        // Escape HTML special characters
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // AJAX Like Handler (for gallery view)
        function likePhoto(photoId) {
            fetch('like_ajax.php?id=' + photoId, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update like count
                    const likeBtn = document.querySelector(`#photo-${photoId} .like-btn`);
                    const likeCount = likeBtn.querySelector('span');
                    likeCount.textContent = data.like_count;
                    
                    // Toggle liked class with animation
                    if (data.liked) {
                        likeBtn.classList.add('liked');
                        // Heart animation
                        const heart = likeBtn.querySelector('i');
                        heart.style.animation = 'heartBeat 0.3s ease';
                        setTimeout(() => heart.style.animation = '', 300);
                    } else {
                        likeBtn.classList.remove('liked');
                    }
                    
                    // Show success message
                    showMessage(data.message, 'success');
                } else {
                    showMessage(data.message || 'Failed to like photo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
            });
            
            return false;
        }

        // Show message notification
        function showMessage(message, type) {
            // Remove existing messages
            const existingMsg = document.querySelector('.messages');
            if (existingMsg) {
                existingMsg.remove();
            }
            
            // Create new message
            const msgDiv = document.createElement('div');
            msgDiv.className = 'messages';
            msgDiv.style.animation = 'slideDown 0.3s ease';
            msgDiv.innerHTML = `
                <div class="container">
                    <div class="${type}-message">
                        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}
                    </div>
                </div>
            `;
            
            document.body.appendChild(msgDiv);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                msgDiv.style.transition = 'opacity 0.5s';
                msgDiv.style.opacity = '0';
                setTimeout(() => msgDiv.remove(), 500);
            }, 3000);
        }

        // Close modal when clicking outside
        document.getElementById('photoModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closePhotoModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePhotoModal();
            }
        });
        
        // Auto-hide initial success messages after 3 seconds
        setTimeout(function() {
            const messages = document.querySelector('.messages');
            if (messages) {
                messages.style.transition = 'opacity 0.5s';
                messages.style.opacity = '0';
                setTimeout(function() {
                    messages.style.display = 'none';
                }, 500);
            }
        }, 3000);
    </script>
    
    <style>
        @keyframes heartBeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .like-btn.liked i {
            color: #ff4757;
            animation: heartBeat 0.3s ease;
        }
        
        .comment-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
SCRIPTS;

include 'footer_logged_in.php';
?>