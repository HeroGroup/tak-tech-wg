<div class="row mb-4">
  <div class="col-sm-4">
    <input type="text" name="search" id="search" class="form-control" placeholder="search comment, address, note" value="{{$search}}">
  </div>
  <div class="col-sm-1">
    <button type="button" class="btn btn-sm btn-primary mt-1" onclick="search()">search</button>
  </div>
</div>

<script>
  var baseRoute = "{{$route}}";
  
  function search() {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('search', document.getElementById("search").value);
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
</script>