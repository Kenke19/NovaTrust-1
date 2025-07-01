const token = localStorage.getItem('token');
if (!token) window.location.href = 'login.html';

document.getElementById('logoutBtn').addEventListener('click', () => {
    localStorage.removeItem('token');
    window.location.href = 'login.html';
});

// You can add shared functions here, e.g. loadAccounts()
function loadAccounts(selectId, callback) {
    fetch('http://localhost/NovaTrust/Backend/account_overview.php', {
    headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(response => response.json())
    .then(data => {
    if (data.accounts && data.accounts.length > 0) {
        const options = data.accounts.map(acc =>
        `<option value="${acc.id}">${acc.account_type.toUpperCase()} (${acc.account_number}) - ₦${parseFloat(acc.balance).toFixed(2)}</option>`
        ).join('');
        document.getElementById(selectId).innerHTML = options;
        if (callback) callback();
    }
    });
}
// Function to load user info
function loadUserInfo() {
    fetch('http://localhost/NovaTrust/Backend/profile.php', {
        headers: { 'Authorization': 'Bearer ' + token }
    })
    .then(response => response.json())
    .then(data => {
        if (data.username) {
            document.getElementById('userInfo').innerHTML = `
                <h2><strong>Welcome, ${data.username}</strong></h2>
                <p>Email: ${data.email}</p>
                <p>Last Login: ${data.previous_login}</p>
                <p>Member Since: <strong>${data.created_at}</strong></p>
            `;
            
        } else {
            document.getElementById('userInfo').textContent = 'Failed to load user info.';
        }
    })
}
