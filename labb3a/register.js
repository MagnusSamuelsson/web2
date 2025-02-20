const username = sessionStorage.getItem('username');
if (username) {
  document.getElementById('username').textContent = username;
  sessionStorage.clear();
} else {
  window.location.href = 'index.html';
}