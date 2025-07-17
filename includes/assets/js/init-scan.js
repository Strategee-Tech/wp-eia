document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    
    const scanBtn = document.getElementById('scan-all-btn');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';
    scanBtn.textContent = 'Scanear ' + totalPages + ' Páginas';

    let currentPage = 0;



    
    scanBtn.addEventListener('click', async function() {
        console.log(JSON.stringify(totalPages));
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{
            currentPage++;
            const response = await fetch(`https://eia2025.strategee.us/wp-json/api/v1/scan-files`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    currentPage: currentPage,
                    totalPages: totalPages
                })
            });
            const data = await response.json();
            console.log(JSON.stringify(data.attachments));

            scanBtn.textContent = currentPage + ' de ' + totalPages;
            await esperarSegundos(3);
        } while (currentPage <= totalPages);
    });
});


function esperarSegundos(segundos) {
    return new Promise(resolve => setTimeout(resolve, segundos * 1000));
}