// assets/js/admin.js

// Глобальные переменные
let currentMsgNumber = null;

// Открытие модального окна ответа
function openReplyModal(msgNumber, email, consultationNumber) {
    currentMsgNumber = msgNumber;
    
    document.getElementById('replyMsgNumber').value = msgNumber;
    document.getElementById('replyConsultNumber').value = consultationNumber;
    document.getElementById('replyTo').value = email;
    document.getElementById('replySubject').value = `Re: ${consultationNumber}`;
    document.getElementById('replyText').value = '';
    
    document.getElementById('replyModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Закрытие модального окна ответа
function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('active');
    document.body.style.overflow = '';
    currentMsgNumber = null;
}

// Отправка ответа
document.getElementById('replyForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        msgNumber: document.getElementById('replyMsgNumber').value,
        to: document.getElementById('replyTo').value,
        subject: document.getElementById('replySubject').value,
        text: document.getElementById('replyText').value,
        method: document.getElementById('replyMethod').value,
        consultationNumber: document.getElementById('replyConsultNumber').value
    };
    
    if (!formData.text.trim()) {
        showNotification('error', 'Ошибка', 'Введите текст ответа');
        return;
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loader"></span> Отправка...';
    
    try {
        const response = await fetch('reply.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Успешно', 'Ответ отправлен заявителю');
            closeReplyModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(result.message || 'Ошибка отправки');
        }
    } catch (error) {
        showNotification('error', 'Ошибка', error.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    }
});

// Просмотр полного письма
async function viewFullEmail(msgNumber) {
    showLoading(true);
    
    try {
        const response = await fetch(`download.php?action=view&msg=${msgNumber}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('viewModalBody').innerHTML = `
                <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                    <div style="background: #f5f7fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong>От:</strong> ${escapeHtml(data.from)}<br>
                        <strong>Тема:</strong> ${escapeHtml(data.subject)}<br>
                        <strong>Дата:</strong> ${data.date}
                    </div>
                    <div style="background: #fff; padding: 20px; border: 1px solid #e9ecef; border-radius: 8px;">
                        ${data.body}
                    </div>
                    ${data.attachments && data.attachments.length > 0 ? `
                        <div style="margin-top: 20px; padding: 15px; background: #f5f7fa; border-radius: 8px;">
                            <strong>Вложения:</strong><br>
                            ${data.attachments.map(att => `
                                <a href="download.php?action=attachment&msg=${msgNumber}&file=${encodeURIComponent(att.filename)}" 
                                   class="attachment-link" style="display: inline-block; margin: 5px; padding: 8px 12px; background: white; border-radius: 6px; text-decoration: none; color: #0056b3;">
                                    📎 ${escapeHtml(att.filename)}
                                </a>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            throw new Error('Ошибка загрузки письма');
        }
    } catch (error) {
        showNotification('error', 'Ошибка', error.message);
    } finally {
        showLoading(false);
    }
}

// Закрытие окна просмотра
function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Скачивание вложения
function downloadAttachment(msgNumber, filename) {
    window.open(`download.php?action=attachment&msg=${msgNumber}&file=${encodeURIComponent(filename)}`, '_blank');
}

// Показ уведомления
function showNotification(type, title, message) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div class="notification-icon">${type === 'success' ? '✓' : type === 'error' ? '✕' : '⚠'}</div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Показ/скрытие лоадера
function showLoading(show) {
    let loader = document.querySelector('.loading-overlay');
    
    if (show) {
        if (!loader) {
            loader = document.createElement('div');
            loader.className = 'loading-overlay';
            loader.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(loader);
        }
        loader.style.display = 'flex';
    } else if (loader) {
        loader.style.display = 'none';
    }
}

// Экранирование HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Автообновление каждые 60 секунд
setInterval(() => {
    if (document.querySelector('.consultations-list')) {
        // Показываем индикатор обновления
        const refreshBtn = document.querySelector('.btn-refresh');
        if (refreshBtn) {
            refreshBtn.style.animation = 'spin 1s linear';
            setTimeout(() => {
                refreshBtn.style.animation = '';
            }, 1000);
        }
    }
}, 60000);

// Закрытие модалок по клику вне
window.addEventListener('click', function(e) {
    if (e.target.id === 'replyModal') {
        closeReplyModal();
    }
    if (e.target.id === 'viewModal') {
        closeViewModal();
    }
});

// Горячие клавиши
document.addEventListener('keydown', function(e) {
    // ESC закрывает модалки
    if (e.key === 'Escape') {
        closeReplyModal();
        closeViewModal();
    }
    
    // Ctrl+R обновляет страницу
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        location.reload();
    }
});