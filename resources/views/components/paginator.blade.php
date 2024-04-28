<div style="font-size: 14px; display:flex;justify-content:space-between;margin-bottom:4px;">
  <div>
    <span id="number-of-selected-items">{{$selectedCount}}</span> items are selected.&nbsp;
  </div>
  <div>
  <a href="#" onclick="previousPage()" id="previous-page-btn" class="disabled pagination-btn"><</a>&nbsp;
    <span id="page-number" class="pagination-btn">1</span>&nbsp;
    <a href="#" onclick="nextPage()" id="next-page-btn" class="pagination-btn">></a>&nbsp;&nbsp;
    <label>records per page: </label>&nbsp;
    <a href="#" id="take-btn-50" onclick="take(50)" class="pagination-btn active">50</a>&nbsp;
    <a href="#" id="take-btn-100" onclick="take(100)" class="pagination-btn">100</a>&nbsp;
    <a href="#" id="take-btn-300" onclick="take(300)" class="pagination-btn">300</a>&nbsp;
    <a href="#" id="take-btn-all" onclick="take('all')" class="pagination-btn">All</a>
  </div>
</div>

<script>
  function previousPage() {
    var queryString = window.location.search || '?';
    var urlParams = new URLSearchParams(queryString);
    var page = urlParams.get('page');
    var newPage = page ? parseInt(page)-1 : 1;
    urlParams.set('page', newPage);

    window.location.href = `{{$route}}?${urlParams.toString()}`;
  }
  function nextPage() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    var page = urlParams.get('page');
    var newPage = page ? parseInt(page)+1 : 2;
    urlParams.set('page', newPage);

    window.location.href = `{{$route}}?${urlParams.toString()}`;
  }
  function take(num) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('take', num);
    urlParams.set('page', 1);

    window.location.href = `{{$route}}?${urlParams.toString()}`;
  }
</script>