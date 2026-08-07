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
                                    <h5>Barcode</h5>
                                </div>
                            </div>
                        </div>

                        <div class="card-body" id="print-area">
                            <div class="row">
                                @for ($i = 0; $i < 6; $i++)
                                    <div class="col-md-2">
                                        <div class="barcode text-center">
                                            <p class="item-name">{{$medicine->medicine_name}} {{$medicine->strength}}</p>
                                            <img src="data:image/png;base64,{{DNS1D::getBarcodePNG($itemCode.$medicine->id, 'C128C')}}" width="50%" alt="barcode"/>
                                            <p class="m-0 p-0 price">{{$itemCode.$medicine->id}}</p>
                                            <p class="m-0 p-0 price">Price: {{$medicine->price}}</p>
                                        </div>
                                    </div>
                                @endfor
                            </div>

                            <div class="row">
                                @for ($i = 0; $i < 6; $i++)
                                    <div class="col-md-2">
                                        <div class="barcode text-center">
                                            <p class="item-name">{{$medicine->medicine_name}} {{$medicine->strength}}</p>
                                            <img src="data:image/png;base64,{{DNS1D::getBarcodePNG($itemCode.$medicine->id, 'C128C')}}" width="50%" alt="barcode"/>
                                            <p class="m-0 p-0 price">{{$itemCode.$medicine->id}}</p>
                                            <p class="m-0 p-0 price">Price: {{$medicine->price}}</p>
                                        </div>
                                    </div>
                                @endfor
                            </div>

                            <div class="row">
                                @for ($i = 0; $i < 6; $i++)
                                    <div class="col-md-2">
                                        <div class="barcode text-center">
                                            <p class="item-name">{{$medicine->medicine_name}} {{$medicine->strength}}</p>
                                            <img src="data:image/png;base64,{{DNS1D::getBarcodePNG($itemCode.$medicine->id, 'C128C')}}" width="50%" alt="barcode"/>
                                            <p class="m-0 p-0 price">{{$itemCode.$medicine->id}}</p>
                                            <p class="m-0 p-0 price">Price: {{$medicine->price}}</p>
                                        </div>
                                    </div>
                                @endfor
                            </div>

                            <div class="row">
                                @for ($i = 0; $i < 6; $i++)
                                    <div class="col-md-2">
                                        <div class="barcode text-center">
                                            <p class="item-name">{{$medicine->medicine_name}} {{$medicine->strength}}</p>
                                            <img src="data:image/png;base64,{{DNS1D::getBarcodePNG($itemCode.$medicine->id, 'C128C')}}" width="50%" alt="barcode"/>
                                            <p class="m-0 p-0 price">{{$itemCode.$medicine->id}}</p>
                                            <p class="m-0 p-0 price">Price: {{$medicine->price}}</p>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 pl-5 pb-5">
                                <a href="#" class="btn-success btn-sm print-btn">
                                    <i class="fas fa-align-justify mr-1 print"></i>Print
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
@endsection
