document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    const scanBtn = document.getElementById('scan-all-btn');
    const scanProgress = document.getElementById('scan-progress');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';

    const url         = `${window.location.origin}/wp-json/api/v1/scan-files`;
    const user        = 'it@strategee.us';
    const password    = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
    const credentials = btoa(`${user}:${password}`);
    let totalScanned = '?';
    
 
    scanBtn.addEventListener('click', async function() {
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{

            scanProgress.textContent = 'Escaneados ' + totalScanned + ' de ' + totalRecords + ' archivos';

            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
                },
                body: JSON.stringify({
                    attachment_id: null,
                    path: null
                })
            });

        } while (totalScanned <= totalRecords);
        spinnerLoader.style.display = 'none';    
        iconScan.style.display = 'block';
    });
});

function esperarSegundos(segundos) {
    return new Promise(resolve => setTimeout(resolve, segundos * 1000));
}