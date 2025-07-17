document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    
    const scanBtn = document.getElementById('scan-all-btn');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';
    scanBtn.textContent = 'Scanear ' + totalPages + ' Páginas';

    let currentPage = 1;



    
    scanBtn.addEventListener('click', async function() {
        console.log(JSON.stringify(totalPages));
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{
            currentPage++;
            scanBtn.textContent = currentPage + ' de ' + totalPages;
            await esperarSegundos(3);
        } while (currentPage <= totalPages);
    });
});


function esperarSegundos(segundos) {
    return new Promise(resolve => setTimeout(resolve, segundos * 1000));
}