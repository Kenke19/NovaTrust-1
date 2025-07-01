window.addEventListener('DOMContentLoaded', () => {
    loadAccounts('from_account_id');
    document.getElementById('transferForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const transferBtn = document.getElementById('transferBtn');
    const transferMsg = document.getElementById('transferMsg');
    transferBtn.disabled = true;
    transferBtn.textContent = 'Processing...';
    transferMsg.textContent = '';
    transferMsg.className = '';
    const from_account_id = document.getElementById('from_account_id').value;
    const to_account_number = document.getElementById('to_account_number').value;
    const amount = document.getElementById('amount').value;
    try {
        const response = await fetch('http://localhost/NovaTrust/Backend/fund_transfer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({ from_account_id, to_account_number, amount })
        });
        const data = await response.json();
        if (response.ok) {
        transferMsg.className = 'success';
        transferMsg.textContent = data.message || 'Transfer successful!';
        document.getElementById('transferForm').reset();
        loadAccounts('from_account_id');
        } else {
        transferMsg.className = 'error';
        transferMsg.textContent = data.error || 'Transfer failed';
        }
    } catch (err) {
        transferMsg.className = 'error';
        transferMsg.textContent = 'Network error: ' + err.message;
    } finally {
        transferBtn.disabled = false;
        transferBtn.textContent = 'Transfer Funds';
    }
    });
});