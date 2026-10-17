<?php
/**
 * ملف JavaScript الرئيسي للموقع
 */
?>
// تهيئة عناصر Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة التلميحات
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // تهيئة البوبوفر
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // إخفاء رسائل التنبيه بعد 5 ثوانٍ
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// دالة للتحقق من صحة نموذج التسجيل
function validateRegistrationForm() {
    var username = document.getElementById('username').value;
    var email = document.getElementById('email').value;
    var password = document.getElementById('password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    var errorMessages = [];
    
    // التحقق من اسم المستخدم
    if (username.length < 3 || username.length > 50) {
        errorMessages.push('اسم المستخدم يجب أن يكون بين 3 و 50 حرفاً');
    }
    
    // التحقق من البريد الإلكتروني
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        errorMessages.push('البريد الإلكتروني غير صالح');
    }
    
    // التحقق من كلمة المرور
    if (password.length < 6) {
        errorMessages.push('كلمة المرور يجب أن تكون على الأقل 6 أحرف');
    }
    
    // التحقق من تطابق كلمة المرور
    if (password !== confirmPassword) {
        errorMessages.push('كلمة المرور وتأكيدها غير متطابقين');
    }
    
    // عرض رسائل الخطأ إذا وجدت
    if (errorMessages.length > 0) {
        var errorContainer = document.getElementById('error-container');
        errorContainer.innerHTML = '';
        
        var errorList = document.createElement('ul');
        errorList.className = 'mb-0';
        
        errorMessages.forEach(function(message) {
            var errorItem = document.createElement('li');
            errorItem.textContent = message;
            errorList.appendChild(errorItem);
        });
        
        errorContainer.appendChild(errorList);
        errorContainer.style.display = 'block';
        
        return false;
    }
    
    return true;
}

// دالة للتحقق من صحة نموذج إضافة/تعديل الأنمي
function validateAnimeForm() {
    var title = document.getElementById('title').value;
    var description = document.getElementById('description').value;
    var releaseYear = document.getElementById('release_year').value;
    var coverImage = document.getElementById('cover_image');
    var errorMessages = [];
    
    // التحقق من العنوان
    if (title.trim() === '') {
        errorMessages.push('عنوان الأنمي مطلوب');
    }
    
    // التحقق من الوصف
    if (description.trim() === '') {
        errorMessages.push('وصف الأنمي مطلوب');
    }
    
    // التحقق من سنة الإصدار
    if (releaseYear.trim() === '' || isNaN(releaseYear) || parseInt(releaseYear) < 1900 || parseInt(releaseYear) > new Date().getFullYear()) {
        errorMessages.push('سنة الإصدار غير صالحة');
    }
    
    // التحقق من صورة الغلاف عند الإضافة
    if (coverImage && coverImage.files.length > 0) {
        var file = coverImage.files[0];
        var fileSize = file.size / 1024 / 1024; // بالميجابايت
        var fileType = file.type;
        
        // التحقق من نوع الملف
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(fileType)) {
            errorMessages.push('نوع ملف الصورة غير مسموح به. الأنواع المسموح بها: JPG, PNG, WebP');
        }
        
        // التحقق من حجم الملف
        if (fileSize > 5) {
            errorMessages.push('حجم الصورة كبير جداً. الحد الأقصى: 5 ميجابايت');
        }
    }
    
    // عرض رسائل الخطأ إذا وجدت
    if (errorMessages.length > 0) {
        var errorContainer = document.getElementById('error-container');
        errorContainer.innerHTML = '';
        
        var errorList = document.createElement('ul');
        errorList.className = 'mb-0';
        
        errorMessages.forEach(function(message) {
            var errorItem = document.createElement('li');
            errorItem.textContent = message;
            errorList.appendChild(errorItem);
        });
        
        errorContainer.appendChild(errorList);
        errorContainer.style.display = 'block';
        
        return false;
    }
    
    return true;
}

// دالة للتحقق من صحة نموذج إضافة/تعديل الحلقة
function validateEpisodeForm() {
    var episodeNumber = document.getElementById('episode_number').value;
    var videoFile = document.getElementById('video_file');
    var errorMessages = [];
    
    // التحقق من رقم الحلقة
    if (episodeNumber.trim() === '' || isNaN(episodeNumber) || parseInt(episodeNumber) < 1) {
        errorMessages.push('رقم الحلقة غير صالح');
    }
    
    // التحقق من ملف الفيديو عند الإضافة
    if (videoFile && videoFile.files.length > 0) {
        var file = videoFile.files[0];
        var fileSize = file.size / 1024 / 1024; // بالميجابايت
        var fileType = file.type;
        
        // التحقق من نوع الملف
        if (!['video/mp4', 'video/webm', 'video/ogg'].includes(fileType)) {
            errorMessages.push('نوع ملف الفيديو غير مسموح به. الأنواع المسموح بها: MP4, WebM, OGG');
        }
        
        // التحقق من حجم الملف
        if (fileSize > 500) {
            errorMessages.push('حجم الفيديو كبير جداً. الحد الأقصى: 500 ميجابايت');
        }
    }
    
    // عرض رسائل الخطأ إذا وجدت
    if (errorMessages.length > 0) {
        var errorContainer = document.getElementById('error-container');
        errorContainer.innerHTML = '';
        
        var errorList = document.createElement('ul');
        errorList.className = 'mb-0';
        
        errorMessages.forEach(function(message) {
            var errorItem = document.createElement('li');
            errorItem.textContent = message;
            errorList.appendChild(errorItem);
        });
        
        errorContainer.appendChild(errorList);
        errorContainer.style.display = 'block';
        
        return false;
    }
    
    return true;
}

// دالة لمعاينة الصورة قبل الرفع
function previewImage(input, previewId) {
    var preview = document.getElementById(previewId);
    
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '';
        preview.style.display = 'none';
    }
}

// دالة لتأكيد الحذف
function confirmDelete(message, url) {
    if (confirm(message || 'هل أنت متأكد من الحذف؟')) {
        window.location.href = url;
    }
    return false;
}

// دالة لتحديث حالة الأنمي في لوحة التحكم
function updateAnimeStatus(animeId, status) {
    fetch('ajax/update_anime_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'anime_id=' + animeId + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تحديث واجهة المستخدم
            var statusBadge = document.getElementById('status-badge-' + animeId);
            if (statusBadge) {
                statusBadge.textContent = status;
                
                // تحديث لون الشارة
                statusBadge.className = 'badge ';
                if (status === 'مستمر') {
                    statusBadge.className += 'bg-primary';
                } else if (status === 'مكتمل') {
                    statusBadge.className += 'bg-success';
                } else if (status === 'متوقف') {
                    statusBadge.className += 'bg-danger';
                } else {
                    statusBadge.className += 'bg-secondary';
                }
            }
            
            // عرض رسالة نجاح
            var alertContainer = document.getElementById('alert-container');
            if (alertContainer) {
                alertContainer.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                    'تم تحديث حالة الأنمي بنجاح' +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
            }
        } else {
            // عرض رسالة خطأ
            var alertContainer = document.getElementById('alert-container');
            if (alertContainer) {
                alertContainer.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    'فشل تحديث حالة الأنمي: ' + data.message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                    '</div>';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // عرض رسالة خطأ
        var alertContainer = document.getElementById('alert-container');
        if (alertContainer) {
            alertContainer.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                'حدث خطأ أثناء معالجة الطلب' +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>';
        }
    });
}

// دالة لتحديث عدد المشاهدات للحلقة
function updateEpisodeViews(episodeId) {
    fetch('ajax/update_views.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'episode_id=' + episodeId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تحديث عداد المشاهدات في واجهة المستخدم إذا وجد
            var viewsCounter = document.getElementById('views-counter');
            if (viewsCounter) {
                viewsCounter.textContent = data.views;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// دالة لتبديل حالة الإعجاب بالتعليق
function toggleCommentLike(commentId) {
    if (!isLoggedIn) {
        window.location.href = 'login.php';
        return;
    }
    
    fetch('ajax/comment_like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'comment_id=' + commentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // تحديث أيقونة الإعجاب
            var likeIcon = document.getElementById('like-icon-' + commentId);
            var likeCount = document.getElementById('like-count-' + commentId);
            
            if (likeIcon && likeCount) {
                if (data.action === 'add') {
                    likeIcon.classList.remove('far');
                    likeIcon.classList.add('fas');
                } else {
                    likeIcon.classList.remove('fas');
                    likeIcon.classList.add('far');
                }
                
                likeCount.textContent = data.likes;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// دالة للبحث المباشر عن الأنمي
function liveSearch() {
    var searchTerm = document.getElementById('live-search').value;
    
    if (searchTerm.length < 2) {
        document.getElementById('search-results').innerHTML = '';
        document.getElementById('search-results').style.display = 'none';
        return;
    }
    
    fetch('ajax/live_search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'search=' + encodeURIComponent(searchTerm)
    })
    .then(response => response.json())
    .then(data => {
        var resultsContainer = document.getElementById('search-results');
        resultsContainer.innerHTML = '';
        
        if (data.length > 0) {
            data.forEach(function(anime) {
                var resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                
                var resultLink = document.createElement('a');
                resultLink.href = 'anime.php?id=' + anime.id;
                resultLink.className = 'd-flex align-items-center p-2';
                
                var resultImage = document.createElement('img');
                resultImage.src = anime.cover_image;
                resultImage.alt = anime.title;
                resultImage.className = 'search-result-image me-2';
                
                var resultInfo = document.createElement('div');
                resultInfo.className = 'search-result-info';
                
                var resultTitle = document.createElement('div');
                resultTitle.className = 'search-result-title';
                resultTitle.textContent = anime.title;
                
                var resultMeta = document.createElement('div');
                resultMeta.className = 'search-result-meta';
                resultMeta.textContent = anime.release_year + ' • ' + anime.status;
                
                resultInfo.appendChild(resultTitle);
                resultInfo.appendChild(resultMeta);
                
                resultLink.appendChild(resultImage);
                resultLink.appendChild(resultInfo);
                
                resultItem.appendChild(resultLink);
                resultsContainer.appendChild(resultItem);
            });
            
            resultsContainer.style.display = 'block';
        } else {
            var noResults = document.createElement('div');
            noResults.className = 'search-no-results p-3 text-center';
            noResults.textContent = 'لا توجد نتائج';
            
            resultsContainer.appendChild(noResults);
            resultsContainer.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// إخفاء نتائج البحث عند النقر خارجها
document.addEventListener('click', function(event) {
    var searchResults = document.getElementById('search-results');
    var liveSearch = document.getElementById('live-search');
    
    if (searchResults && liveSearch && !searchResults.contains(event.target) && !liveSearch.contains(event.target)) {
        searchResults.style.display = 'none';
    }
});
