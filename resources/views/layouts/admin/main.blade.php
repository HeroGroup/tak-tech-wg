<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | {{$pageTitle}}</title>

    <!-- Custom fonts for this template-->
    <link href="/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

    <!-- Custom styles for this template-->
    <link href="/assets/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="/assets/css/colored-toast.css" rel="stylesheet">
    <link href="/assets/css/selectize.bootstrap3.min.css" rel="stylesheet">
    <link href="/assets/css/slider.css" rel="stylesheet">
    <link href="/vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    
    <style>
        .is-active-indicator {
            background-color:#eaecf4;
            display: inline-block;
            height:.75rem;
            width:.75rem;
            border-radius:100%;
            /* position:initial; */
            /* bottom:0;
            right:0; */
            border:.125rem solid #fff;
        }
        .filters-container {
            display:flex;
            justify-content:space-between;
            border: 1px solid lightgray;
            border-radius:10px;
            background-color: #fff;
            padding:.5em;
            align-items:center;
        }
        .filter-btns {
            display: flex;
        }
        .filter-dropdown {
            display: none;
            margin-right: .5em;
        }
        .filter-btn {
            border: 1px solid lightgray; 
            border-radius: 10px; 
            background-color: #fff; 
            display: inline-block;
            padding: .5em;
            margin-right: .5em;
            text-decoration: none;
        }
        .filter-btn:hover {
            background-color: #eee;
            text-decoration: none;
        }
        .date-search-box {
            display: flex;
            border: 1px solid lightgray;
            border-radius:10px;
            background-color: #fff;
            padding:.5em;
            align-items:center;
            width: 100%;
        }
        @media (max-width: 670px) {
            .filter-btns {
                display: none;
            }
            .filter-dropdown {
                display: block;
            }
        }
        #loader-overlay {
            position: fixed;
            display: none;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0,0,0,0.5);
            z-index: 1100;
        }

        .loader {
            border: 6px solid #f3f3f3;
            border-radius: 50px;
            border-top: 6px solid #64498E;
            border-right: 6px solid #64498E;
            position: absolute;
            top: 45%;
            left: 45%;
            width: 40px;
            height: 40px;
            -webkit-animation: spin 2s linear infinite;
            animation: spin 1s linear infinite;
        }
        @-webkit-keyframes spin {
            0% { -webkit-transform: rotate(0deg); }
            100% { -webkit-transform: rotate(360deg); }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Bootstrap core JavaScript-->
    <script src="/vendor/jquery/jquery.min.js"></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</head>

<body id="page-top">

<!-- Page Wrapper -->
<div id="wrapper">
    @include('layouts.admin.sidebar')

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">

            @include('layouts.admin.topbar')

            <div class="container-fluid">
                <div style="display:flex;justify-content:space-between;padding-bottom:10px">
                    <h1 class="h3 mb-2 text-gray-800">{{$pageTitle}}</h1>
                    @if(isset($newButton))
                        <a href="{{isset($newButtonUrl) ? $newButtonUrl : '#'}}" class="btn btn-primary btn-icon-split">
                            <span class="icon text-white-50">
                                <i class="fas fa-plus"></i>
                            </span>
                            <span class="text">{{isset($newButtonText) ? $newButtonText : 'add'}}</span>
                        </a>
                    @endif
                </div>

                @yield('content')

            </div>

        </div>
        <!-- End of Main Content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; <b>{{env('APP_NAME')}}</b> <span id="current-year"></span></span>
                </div>
            </div>
        </footer>
        <!-- End of Footer -->

    </div>
    <!-- End of Content Wrapper -->
</div>
<!-- End of Page Wrapper -->

<!-- Scroll to Top Button-->
<a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<!-- Core plugin JavaScript-->
<script src="/vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="/assets/js/sb-admin-2.min.js"></script>
<script src="/assets/js/sweetalert2.min.js"></script>
<script src="/assets/js/selectize.min.js"></script>
<script src="/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="/vendor/datatables/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        document.getElementById("current-year").innerHTML = getYear();

        if("{{\Illuminate\Support\Facades\Session::has('message')}}" === "1") {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                iconColor: 'white',
                customClass: {
                    popup: 'colored-toast'
                },
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            Toast.fire({
                icon: "{{\Illuminate\Support\Facades\Session::get('type')}}" === 'success' ? 'success' : 'error',
                title: "{{\Illuminate\Support\Facades\Session::get('message')}}"
            });
        }

        $('#dataTable').DataTable({ order: false, pageLength: 100 });

        $('select:not(select[name=DataTables_Table_0_length])').selectize({
            sortField: 'text'
        });
    });

    function destroy(route,id,elementId) {
        event.preventDefault();

        Swal.fire({
            title: "Delete this record?",
            text: "Bear in mind that deleting is irreversible!",
            icon: "error",
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: 'lightgray',
            confirmButtonText: 'Delete'
        }).then((result) => {
            if (result.isConfirmed) {
                sendRequest({
                    method: 'POST',
                    route,
                    formData: createFormData({
                        '_token': '{{csrf_token()}}',
                        '_method': 'DELETE',
                        'id': id
                    }),
                    successCallback: function () {
                        document.getElementById(elementId).remove();
                    }
                });
            }
        });
    }

    function getYear() {
        var now = new Date();
        return now.getFullYear();
    }

    function createFormData(inputs) {
        let formData = new FormData();
        var inputKeys = Object.keys(inputs);
        inputKeys.forEach((key) => {
        formData.append(key, inputs[key]);
        });

        return formData;
    }
    
    function sendRequest(params) {
        var xhr = new XMLHttpRequest();
        xhr.open(params.method, params.route, true);
        xhr.addEventListener("load", function() {
        var response = JSON.parse(xhr.response);
        if(response.status === 1) {
            if(params.successCallback) {
            params.successCallback();
            }
            
            Swal.fire({
                position: 'top-end',
                icon: 'success',
                title: response.message,
                showConfirmButton: false,
                timer: parseInt("{{$messageDuration ?? '3000'}}")
            })
        } else {
            if (params.failCallback) {
            params.failCallback();
            }

            Swal.fire({
            position: 'top-end',
            icon: 'error',
            title: response.message,
            showConfirmButton: false,
            timer: parseInt("{{$messageDuration ?? '3000'}}")
            })
        }
        });
        xhr.send(params.formData);
    }
</script>
</body>
</html>
