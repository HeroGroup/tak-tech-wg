<div id="loader-overlay">
  <div class="loader"></div>
</div>
<script>
window.onunload = function(event) { turnOffLoader(); };

function turnOnLoader() {
  document.getElementById("loader-overlay").style.display = "block";
}

function turnOffLoader() {
  document.getElementById("loader-overlay").style.display = "none";
}
</script>
