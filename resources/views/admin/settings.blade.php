@extends('layouts.admin.main', ['pageTitle' => 'Settings', 'active' => 'settings'])
@section('content')

<x-loader/>

<a href="#" class="btn btn-primary btn-icon-split mb-4" data-toggle="modal" data-target="#new-setting-modal">
    <span class="icon text-white-50">
        <i class="fas fa-plus"></i>
    </span>
    <span class="text">Add setting</span>
</a>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <th>Key</th>
            <th>Value</th>
            <th>Actions</th>
        </thead>
        <tbody>
            @foreach($settings as $setting)
            <tr id="{{$setting->id}}">
                <td>{{$setting->setting_key}}</td>
                <td>{{$setting->setting_value}}</td>
                <td>
                <a href="#" class="btn btn-info btn-circle btn-sm" data-toggle="modal" data-target="#edit-setting-modal-{{$setting->id}}" title="Edit">
                <i class="fas fa-pen"></i>
            </a>
            &nbsp;

            <!-- Edit setting Modal -->
            <div class="modal fade" id="edit-setting-modal-{{$setting->id}}" tabindex="-1" role="dialog" aria-labelledby="editsettingModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editsettingModalLabel">Edit setting</h5>
                            <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="{{route('admin.settings.update')}}" onsubmit="turnOnLoader()">
                                @csrf
                                <input type="hidden" name="_method" value="PUT">
                                <input type="hidden" name="id" value="{{$setting->id}}">
                                <div class="form-group row" style="margin-bottom:30px;">
                                    <div class="col-md-12">
                                        <label for="setting_value">{{$setting->setting_key}}</label>
                                        <input class="form-control" name="setting_value" value="{{$setting->setting_value}}" placeholder="Enter Value" required>
                                    </div>
                                </div>
                                <div class="form-group row" style="margin-bottom:30px;">
                                    <div class="col-md-12" style="text-align:center;">
                                        <input type="submit" class="btn btn-success" value="Save and close" />
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <a href="#" class="btn btn-danger btn-circle btn-sm" title="Delete" onclick="destroy('{{route('admin.settings.delete')}}','{{$setting->id}}','{{$setting->id}}')">
                <i class="fas fa-trash"></i>
            </a>
            &nbsp;
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="modal fade" id="new-setting-modal" tabindex="-1" role="dialog" aria-labelledby="newsettingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newsettingModalLabel">Add new setting</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{route('admin.settings.add')}}" onsubmit=""turnOnLoader()">
                    @csrf
                    <div class="form-group row" style="margin-bottom:30px;">
                        <div class="col-md-12">
                            <label for="setting_key">Key</label>
                            <input class="form-control" name="setting_key" value="{{old('setting_key')}}" placeholder="Enter Key" required>
                        </div>
                    </div>
                    <div class="form-group row" style="margin-bottom:30px;">
                        <div class="col-md-12">
                            <label for="setting_value">Value</label>
                            <input class="form-control" name="setting_value" value="{{old('setting_value')}}" placeholder="Enter Value" required>
                        </div>
                    </div>
                    <div class="form-group row" style="margin-bottom:30px;">
                        <div class="col-md-12" style="text-align:center;">
                            <input type="submit" class="btn btn-success" value="Save and close" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection