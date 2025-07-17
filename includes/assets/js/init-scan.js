document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    
    const scanBtn = document.getElementById('scan-all-btn');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';
    scanBtn.textContent = 'Scanear ' + totalPages + ' Páginas';

    let currentPage = 1;

    
    scanBtn.addEventListener('click', function() {
        console.log(JSON.stringify(totalPages));
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';
        scanBtn.textContent = 'Scaneando...';
    });
});