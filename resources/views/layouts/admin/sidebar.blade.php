<style>
    .toggled > .sidebar-brand {
        height:3rem;
    }
</style>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

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


    <li class="nav-item" id="interfaces">
        <a class="nav-link" href="{{route('wiregaurd.interfaces.usages')}}">
            <i class="fas fa-fw fa-database"></i>
            <span>Interfaces</span>
        </a>
    </li>
    <!-- <li class="nav-item" id="interfaces">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseInterfaces" aria-expanded="false" aria-controls="collapseInterfaces">
            <i class="fas fa-fw fa-database"></i>
            <span>Interfaces</span>
        </a>
        <div id="collapseInterfaces" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{route('admin.wiregaurd.interfaces')}}">
                    <i class="fas fa-fw fa-database"></i>
                    <span>List</span>
                </a>
                <a class="collapse-item" href="{{route('wiregaurd.interfaces.usages')}}">
                    <i class="fas fa-fw fa-list-ul"></i>
                    <span>Interfaces Usages</span>
                </a>
            </div>
        </div>
    </li> -->


    <li class="nav-item" id="peers">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePeers" aria-expanded="false" aria-controls="collapsePeers">
            <i class="fas fa-fw fa-sliders-h"></i>
            <span>Peers</span>
        </a>
        <div id="collapsePeers" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{route('wiregaurd.peers.index')}}">
                    <i class="fas fa-fw fa-sliders-h"></i>
                    <span>List</span>
                </a>
                <a class="collapse-item" href="{{route('wiregaurd.peers.limited.list')}}">
                    <i class="fas fa-fw fa-list-ul"></i>
                    <span>Limited Peers</span>
                </a>
                @if(auth()->user()->can_create)
                <a class="collapse-item" href="{{route('wiregaurd.peers.create')}}">
                    <i class="fas fa-fw fa-plus"></i>
                    <span>Create Peers</span>
                </a>
                @endif
                <a class="collapse-item" href="{{route('wiregaurd.peers.actions')}}">
                    <i class="fas fa-fw fa-tools"></i>
                    <span>Peers Actions</span>
                </a>
                <a class="collapse-item" href="{{route('wiregaurd.peers.limited.removedPeers')}}">
                    <i class="fas fa-fw fa-database"></i>
                    <span>Limited Database</span>
                </a>
                @if(auth()->user()->is_admin)
                <a class="collapse-item" href="{{route('admin.wiregaurd.peers.restrictions')}}">
                    <i class="fas fa-fw fa-ban"></i>
                    <span>Peers Restrictions</span>
                </a>
                @endif
            </div>
        </div>
    </li>
    
    @if(auth()->user()->access_violations)
    <li class="nav-item" id="violations">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseviolations" aria-expanded="false" aria-controls="collapseviolations">
            <i class="fas fa-fw fa-exclamation"></i>
            <span>Violations</span>
        </a>
        <div id="collapseviolations" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{route('violations.suspect.list')}}">
                    <i class="fas fa-fw fa-exclamation"></i>
                    <span>Suspect List</span>
                </a>
                <a class="collapse-item" href="{{route('violations.block.list')}}">
                    <i class="fas fa-fw fa-times"></i>
                    <span>Block List</span>
                </a>
                <a class="collapse-item" href="{{route('violations.block.history')}}">
                    <i class="fas fa-fw fa-history"></i>
                    <span>Block History</span>
                </a>
            </div>
        </div>
    </li>
    @endif
    <li class="nav-item" id="monitor">
        <a class="nav-link" href="{{route('wiregaurd.interfaces.usages.monitor.only')}}">
            <i class="fas fa-fw fa-tv"></i>
            <span>Monitor</span>
        </a>
    </li>
    @if(auth()->user()->isAdmin)
    <li class="nav-item" id="servers">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseServers" aria-expanded="false" aria-controls="collapseServers">
            <i class="fas fa-fw fa-server"></i>
            <span>Servers</span>
        </a>
        <div id="collapseServers" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar" style="">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{route('admin.settings.servers.list')}}" id="servres">
                    <i class="fas fa-fw fa-list"></i>
                    <span>Servers</span>
                </a>
                <a class="collapse-item" href="{{route('admin.settings.servers.report')}}" id="servers-report">
                    <i class="fas fa-fw fa-file-contract"></i>
                    <span>Servers Report</span>
                </a>
            </div>
        </div>
    </li>

    <li class="nav-item" id="logs">
        <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseLogs" aria-expanded="false" aria-controls="collapseLogs">
            <i class="fas fa-fw fa-history"></i>
            <span>Logs</span>
        </a>
        <div id="collapseLogs" class="collapse" aria-labelledby="headingTwo" data-parent="#accordionSidebar" style="">
            <div class="bg-white py-2 collapse-inner rounded">
                <a class="collapse-item" href="{{route('admin.logs.cronJobs')}}">
                    <i class="fas fa-fw fa-history"></i>
                    <span>Cron Jobs</span>
                </a>
            </div>
        </div>
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
