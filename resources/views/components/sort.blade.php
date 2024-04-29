<div class="row mb-4">
  <div class="col-sm-12">
    <div class="card">
      <div class="card-body" style="padding:5px 10px;">
        <label>Sort By: </label>
        @foreach($sorts as $key => $value)
        <a href="#" onclick="sortResult('{{$key}}'+'_asc')" class="btn sort-btn @if($sortBy==$key.'_asc') btn-dark @endif" id="sort_"+"{{$key}}"+"_asc">{{$value}} <i class="fa fa-sort-amount-down-alt"></i></a>
        <a href="#" onclick="sortResult('{{$key}}'+'_desc')" class="btn sort-btn @if($sortBy==$key.'_desc') btn-dark @endif" id="sort_"+"{{$key}}"+"_desc">{{$value}} <i class="fa fa-sort-amount-down"></i></a>
        @endforeach
      </div>
    </div>
  </div>
</div>
<script>
  var baseRoute = "{{$route}}";
  function sortResult(sortBy) {
    var queryString = window.location.search;
    var urlParams = new URLSearchParams(queryString);
    urlParams.set('sortBy', sortBy);
    urlParams.delete('page');

    window.location.href = `${baseRoute}?${urlParams.toString()}`;
  }
</script>