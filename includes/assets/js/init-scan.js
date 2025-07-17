document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    const scanBtn = document.getElementById('scan-all-btn');
    const scanProgress = document.getElementById('scan-progress');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';

    let currentPage = 0;
    const totalPages = Math.ceil(totalRecords / 70);

    const url         = `${window.location.origin}/wp-json/api/v1/scan-files`;
    const user        = 'it@strategee.us';
    const password    = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
    const credentials = btoa(`${user}:${password}`);
 
    scanBtn.addEventListener('click', async function() {
        console.log(JSON.stringify(totalRecords));
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{

            currentPage++;
            scanProgress.textContent = currentPage + ' de ' + totalPages;

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
                },
                body: JSON.stringify({
                    totalRecords: totalRecords,
                    currentPage: currentPage
                })
            });
            const data = await response.json();
            console.log(JSON.stringify(data.attachments));

        } while (currentPage <= totalPages);
    });
});


function esperarSegundos(segundos) {
    return new Promise(resolve => setTimeout(resolve, segundos * 1000));
}