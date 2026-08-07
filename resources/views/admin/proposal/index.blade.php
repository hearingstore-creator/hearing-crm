@extends('admin.layouts.master')

@section('content')
<!-- Main content -->
<section class="content pt-3">
   <div class="container-fluid">
      <div class="row">
         <div class="col-md-12">
            <div class="card card-outline card-primary">
               <div class="card-header">
                  <div class="row">
                     <div class="col-md-6">
                        <h5>Proposal List</h5>
                     </div>
                     @can('permission', 'proposals.create')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/proposal/create')}}" class="btn-primary btn-cs-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Proposal</a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                <table  class="table table-bordered table-striped"
                 data-datatable="proposal-list">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Title</th>
                            <th>Send Type</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Proposal Date</th>
                            <th>Valid Until</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proposals as $key=> $val)
                         <tr>
                            <td>{{++$key}}</td>
                            <td>{{ $val->title }}</td>
                            <td>{{ $val->customer_id ? 'Customer' : 'Custom Email' }}</td>
                            <td>{{ $val->customer ? $val->customer->lead->full_name : 'N/A' }}</td>
                            <td>{{ $val->email ?? 'N/A' }}</td>
                            <td>{{ $val->proposal_date ? date('d M Y', strtotime($val->proposal_date)) : 'N/A' }}</td>
                            <td>{{ $val->valid_until ? date('d M Y', strtotime($val->valid_until)) : 'N/A' }}</td>
                            <td>{{ $val->total_amount ? number_format($val->total_amount, 2) : 'N/A' }}</td>
                            <td>
                                @if($val->status === \App\Enums\ProposalStatus::ACCEPTED->value)
                                    <span class="badge badge-success">{{ $val->status }}</span>
                                @elseif($val->status === \App\Enums\ProposalStatus::DECLINED->value)
                                    <span class="badge badge-danger">{{ $val->status }}</span>
                                @elseif($val->status === \App\Enums\ProposalStatus::NEW->value)
                                    <span class="badge badge-info">{{ $val->status }}</span>
                                @elseif($val->status === \App\Enums\ProposalStatus::DRAFT->value)
                                    <span class="badge badge-secondary">{{ $val->status }}</span>
                                @else
                                    <span class="badge badge-light">{{ $val->status ?? 'N/A' }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-center">
                                    @can('permission', 'proposals.index')
                                    <a href="{{ route('proposal.show', $val->id) }}" class="view btn btn-info btn-sm mr-1" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @endcan
                                    @can('permission', 'proposals.edit')
                                    <a href="{{ route('proposal.edit', $val->id) }}" class="edit btn btn-success btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    @can('permission', 'proposals.delete')
                                    <form action="{{ route('proposal.destroy', $val->id) }}" method="POST">
                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                        <input type="hidden" name="_method" value="delete">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                         </tr>
                        @endforeach
                    </tbody>
                </table>
              </div>
            </div>
         </div>
      </div>
   </div>
   <!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection
