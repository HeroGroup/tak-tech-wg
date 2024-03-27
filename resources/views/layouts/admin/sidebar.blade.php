<style>
    .toggled > .sidebar-brand {
        height:3rem;
    }
</style>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion toggled" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <!-- <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/"> -->
        <!-- <img src="/images/Logo.png" alt="takvpn" style="width:100%;" /> -->
        <!-- <div class="sidebar-brand-text mx-3"> -->
            <!-- Logo -->
        <!-- </div> -->
    <!-- </a> -->

    <!-- Divider -->
    <!-- <hr class="sidebar-divider my-0"> -->

    <!-- Nav Item - Dashboard -->
    <li class="nav-item" id="dashboard">
        <a class="nav-link" href="{{route('dashboard')}}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <!-- Divider -->
    <!-- <hr class="sidebar-divider my-0"> -->

    @if(auth()->user()->isAdmin)
    <li class="nav-item" id="interfaces">
        <a class="nav-link" href="{{route('admin.wiregaurd.interfaces')}}">
            <i class="fas fa-fw fa-database"></i>
            <span>Interfaces</span>
        </a>
    </li>
    @endif
    <li class="nav-item" id="peers">
        <a class="nav-link" href="{{route('wiregaurd.peers.index')}}">
            <i class="fas fa-fw fa-sliders-h"></i>
            <span>Peers List</span>
        </a>
    </li>
    <li class="nav-item" id="create_peers">
        <a class="nav-link" href="{{route('wiregaurd.peers.create')}}">
            <i class="fas fa-fw fa-plus"></i>
            <span>Create Peers</span>
        </a>
    </li>
    
    <li class="nav-item" id="limited-peers">
        <a class="nav-link" href="{{route('wiregaurd.peers.limited.list')}}">
            <i class="fas fa-fw fa-list-ul"></i>
            <span>Limited Peers</span>
        </a>
    </li>
    
    @if(auth()->user()->isAdmin)
    <li class="nav-item" id="servers">
        <a class="nav-link" href="{{route('admin.settings.servers.list')}}">
            <i class="fas fa-fw fa-server"></i>
            <span>Servers</span>
        </a>
    </li>

    <li class="nav-item" id="servers_report">
        <a class="nav-link" href="{{route('admin.settings.servers.report')}}">
            <i class="fas fa-fw fa-file-contract"></i>
            <span>Servers Report</span>
        </a>
    </li>

    <li class="nav-item" id="settings">
        <a class="nav-link" href="{{route('admin.settings')}}">
            <i class="fas fa-fw fa-cog"></i>
            <span>Settings</span>
        </a>
    </li>
    
    <!-- Divider -->
    <!-- <hr class="sidebar-divider d-none d-md-block"> -->
    
    <li class="nav-item" id="users">
        <a class="nav-link" href="{{route('admin.users')}}">
            <i class="fas fa-fw fa-users"></i>
            <span>Users</span>
        </a>
    </li>
    @endif
    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler (Sidebar) -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle"></button>
    </div>
</ul>

<script>
    window.onload = function() {
        var active = "{{$active}}";
        document.getElementById(active).classList.add("active");
    }
</script>
<!-- End of Sidebar -->
