document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    
    const scanBtn = document.getElementById('scan-all-btn');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';
    scanBtn.textContent = 'Scanear ' + totalPages + ' Páginas';

    let currentPage = 1;

    console.log(totalPages);

    scanBtn.addEventListener('click', function() {
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';
        scanBtn.textContent = 'Scaneando...';
    });
});