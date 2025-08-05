document.addEventListener('DOMContentLoaded', function() {
    const spinnerLoader = document.getElementById('spinner-loader');
    const iconScan = document.getElementById('icon-scan');
    const scanBtn = document.getElementById('scan-all-btn');
    const scanProgress = document.getElementById('scan-progress');
    spinnerLoader.style.display = 'none';    
    iconScan.style.display = 'block';

    const url         = `${window.location.origin}/wp-json/api/v1/scan-files`;
    const user        = infoCredentials.user_auth;
    const password    = infoCredentials.pass_auth;
    const credentials = btoa(`${user}:${password}`);
    let totalScanned = '?';  
 
    scanBtn.addEventListener('click', async function() {
        spinnerLoader.style.display = 'block';
        iconScan.style.display = 'none';

        do{

            scanProgress.textContent = 'Escaneados ' + totalScanned + ' de ' + totalRecords + ' archivos';

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
                },
                body: JSON.stringify({
                })
            });

            const data = await response.json();
            totalScanned = data.total_escaneados;

        } while (totalScanned <= totalRecords);
        spinnerLoader.style.display = 'none';    
        iconScan.style.display = 'block';
    });
});