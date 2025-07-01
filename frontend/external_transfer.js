window.addEventListener('DOMContentLoaded', () => {
  loadAccounts('ext_from_account_id');
  document.getElementById('externalTransferForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const transferBtn = document.getElementById('externalTransferBtn');
    const transferMsg = document.getElementById('externalTransferMsg');
    transferBtn.disabled = true;
    transferBtn.textContent = 'Processing...';
    transferMsg.textContent = '';
    transferMsg.className = '';
    const from_account_id = document.getElementById('ext_from_account_id').value;
    const bank_code = document.getElementById('bank_code').value;
    const account_number = document.getElementById('ext_account_number').value;
    const amount = document.getElementById('ext_amount').value;
    // Simulate account name for mock mode
    const accountName = document.getElementById('verifiedAccountName').textContent || 'Test User';
    if (!confirm(`Confirm transfer of ₦${amount} to:\n\n${accountName}\n${account_number}\n\nProceed?`)) {
      transferBtn.disabled = false;
      transferBtn.textContent = 'Send to Bank';
      return;
    }
    try {
      const response = await fetch('http://localhost/NovaTrust/Backend/external_transfer.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + token
        },
        body: JSON.stringify({ from_account_id, bank_code, account_number, amount })
      });
      const data = await response.json();
      if (response.ok) {
        transferMsg.className = 'success';
        transferMsg.textContent = data.message || 'Transfer initiated successfully!';
        if (data.reference) {
          transferMsg.textContent += `\nReference: ${data.reference}`;
        }
        document.getElementById('externalTransferForm').reset();
        document.getElementById('accountNameDisplay').style.display = 'none';
        loadAccounts('ext_from_account_id');
      } else {
        transferMsg.className = 'error';
        transferMsg.textContent = data.error || 'Transfer failed';
        if (data.details) transferMsg.textContent += `\n${data.details}`;
      }
    } catch (err) {
      transferMsg.className = 'error';
      transferMsg.textContent = 'Network error: ' + err.message;
    } finally {
      transferBtn.disabled = false;
      transferBtn.textContent = 'Send to Bank';
    }
  });
});