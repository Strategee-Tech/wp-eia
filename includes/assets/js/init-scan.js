document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    
    const scanBtn = document.getElementById('scan-all-btn');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';
    scanBtn.textContent = 'Scanear ' + totalPages + ' Páginas';

    let currentPage = 0;


    const url         = `${window.location.origin}/wp-json/api/v1/scan-files`;
    const user        = 'it@strategee.us';
    const password    = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
    const credentials = btoa(`${user}:${password}`);



    
    scanBtn.addEventListener('click', async function() {
        console.log(JSON.stringify(totalPages));
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{
            currentPage++;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
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