const photoFileEl = document.getElementById('photo-file');
if (photoFileEl) {
    photoFileEl.addEventListener('change', function (e) {
        const fileName = e.target.files[0]?.name || 'No file chosen';
        const label = document.querySelector('.file-input-label');
        if (label) {
            label.textContent = fileName.length > 20 ? fileName.substring(0, 17) + '...' : fileName;
        }
    });
}

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            window.scrollTo({
                top: target.offsetTop - 80,
                behavior: 'smooth'
            });
        }
    });
});