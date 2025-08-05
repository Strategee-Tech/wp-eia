async function scanFile(attachment_id = null, path = null) {

    const url         = `${window.location.origin}/wp-json/api/v1/scan-files`;
    const user        = infoCredentials.user_auth;
    const password    = infoCredentials.pass_auth;
    const credentials = btoa(`${user}:${password}`);

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Basic ${credentials}`
        },
        body: JSON.stringify({
            attachment_id: attachment_id,
            path: path
        })
    });

    const data = await response.json();

    const result = JSON.parse(JSON.stringify(data));
    return result;
}
