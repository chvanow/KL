let currentOpenModal = null;

document.addEventListener('click', function(e) {
    const card = e.target.closest('.employee-card');
    const modal = document.getElementById('cardModal');
    
    if (card) {
        e.stopPropagation();
        showEmployeeCard(card);
    } else if (!e.target.closest('.card-modal')) {
        hideModal();
    }
});

function showEmployeeCard(cardElement) {
    // Закрываем предыдущую модалку если есть
    if (currentOpenModal && currentOpenModal !== cardElement) {
        hideModal();
    }
    
    const name = cardElement.dataset.name || '';
    const position = cardElement.dataset.position || '';
    const phone = cardElement.dataset.phone || '';
    const email = cardElement.dataset.email || '';
    const photo = cardElement.dataset.photo || '';
    const showPhoto = cardElement.dataset.showPhoto === '1';
    
    // Если модалка уже открыта для этого блока - закрываем
    if (currentOpenModal === cardElement) {
        hideModal();
        return;
    }
    
    let photoHtml = '';
    if (showPhoto && photo) {
        photoHtml = `<div class="employee-photo"><img src="${photo}" alt=""></div>`;
    }
    
    let contactHtml = '';
    if (phone || email) {
        contactHtml = `
            <div class="contact-info">
                ${phone ? `
                <div class="contact-item">
                    <svg class="copy-icon" onclick="copyToClipboard('${phone}', 'phone')" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span>${phone}</span>
                </div>` : ''}
                ${email ? `
                <div class="contact-item">
                    <svg class="copy-icon" onclick="copyToClipboard('${email}', 'email')" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span>${email}</span>
                </div>` : ''}
            </div>
        `;
    }
    
    const modal = document.getElementById('cardModal');
    modal.innerHTML = `
        <div class="modal-card-content">
            ${photoHtml}
            <h3>${name}</h3>
            <div class="position">${position}</div>
            ${contactHtml}
        </div>
    `;
    
    const cardRect = cardElement.getBoundingClientRect();
    const modalWidth = 280;
    const modalHeight = 250;
    
    let left = cardRect.right + 10;
    let top = cardRect.top;
    
    if (left + modalWidth > window.innerWidth) {
        left = cardRect.left - modalWidth - 10;
    }
    
    if (top + modalHeight > window.innerHeight) {
        top = window.innerHeight - modalHeight - 20;
    }
    
    modal.style.left = left + 'px';
    modal.style.top = top + 'px';
    modal.style.display = 'block';
    
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    currentOpenModal = cardElement;
}

function hideModal() {
    const modal = document.getElementById('cardModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
            modal.innerHTML = '';
        }, 200);
    }
    currentOpenModal = null;
}

function copyToClipboard(text, type) {
    navigator.clipboard.writeText(text).then(() => {
        // Показываем уведомление
        const notification = document.createElement('div');
        notification.className = 'copy-notification';
        notification.textContent = type === 'phone' ? 'Телефон скопирован' : 'Email скопирован';
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 2000);
    }).catch(err => {
        console.error('Ошибка копирования:', err);
    });
}