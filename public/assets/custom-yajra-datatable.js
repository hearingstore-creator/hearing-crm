// Reusable Yajra DataTable initializer

$(function () {
    $('table[data-datatable]').each(function () {
        var $table = $(this);
        var datatableType = $table.data('datatable');
        var ajaxUrl = $table.data('ajax-url');
        var serverSide = true;
        var fileName = '';
        var title = '';
        var columns = [];

        // Configure based on type
        if (datatableType === 'sale-list') {
            fileName = 'SaleList';
            title = 'Sale List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'sale_invoice_no', name: 'sale_invoice_no'},
                {data: 'name', name: 'name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {
                    data: 'action',
                    name: 'action',
                    orderable: true,
                    searchable: true
                },
            ];
        } else if (datatableType === 'purchase-list') {
            fileName = 'PurchaseList';
            title = 'Purchase List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'purchase_invoice_no', name: 'purchase_invoice_no'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {
                    data: 'action',
                    name: 'action',
                    orderable: true,
                    searchable: true
                },
            ];
        } else if (datatableType === 'discount-list') {
            fileName = 'DiscountList';
            title = 'Discount List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'sale_invoice_no', name: 'sale_invoice_no'},
                {data: 'name', name: 'name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {data: 'total_discount', name: 'total_discount'},
                {data: 'paid', name: 'paid'},
                {data: 'due', name: 'due'},
                {
                    data: 'action',
                    name: 'action',
                    orderable: true,
                    searchable: true
                },
            ];
        } else if (datatableType === 'customer-ledger') {
            var fileName = 'CustomerList';
            var title = 'Customer List';
            
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'name', name: 'name'},
                {data: 'email', name: 'email'},
                {data: 'mobile_no', name: 'mobile_no'},
                {data: 'phone', name: 'phone'},
                {data: 'address1', name: 'address1'},
                {data: 'address2', name: 'address2'},
                {data: 'fax', name: 'fax'},
                {data: 'state', name: 'state'},
                {data: 'country', name: 'country'},
                {data: 'status', name: 'status'},

                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'manufacturer-list') {            
            var fileName = 'ManufacturerList';
            var title = 'Manufacturer List';

            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'email1', name: 'email1'},
                {data: 'mobile_no', name: 'mobile_no'},
                {data: 'phone', name: 'phone'},
                {data: 'address1', name: 'address1'},
                {data: 'fax', name: 'fax'},
                {data: 'state', name: 'state'},
                {data: 'country', name: 'country'},
                {data: 'status', name: 'status'},

                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'category-list') {
            var fileName = 'CategoryList';
            var title = 'Category List';
            serverSide = false;
        } else if (datatableType === 'unit-list') {
            var fileName = 'UnitList';
            var title = 'Unit List';
            serverSide = false;
        } else if (datatableType === 'leaf-settings-list') {
            var fileName = 'LeafSettings';
            var title = 'Leaf Settings';
            serverSide = false;
        } else if (datatableType === 'type-list') {
            var fileName = 'TypeList';
            var title = 'Type List';
            serverSide = false;
        } else if (datatableType === 'medicine-list') {
            var fileName = 'MedicineList';
            var title = 'Medicine List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'medicine_name', name: 'medicine_name'},
                {data: 'generic_name', name: 'generic_name'},
                {data: 'category_name', name: 'category_name'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'shelf', name: 'shelf'},
                {data: 'price', name: 'price'},
                {data: 'cost', name: 'cost'},
                {data: 'strength', name: 'strength'},
                {data: 'image', name: 'image'},
                {data: 'status', name: 'status'},

                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true,
                    width: '90px'
                },

            ];
        } else if (datatableType === 'manufacturer-return-list') {
            var fileName = 'ManufacturerReturnList';
            var title = 'Manufacturer Return List';

            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'purchase_invoice_no', name: 'purchase_invoice_no'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'wastage-return-list') {
            var fileName = 'WastageReturnList'; 
            var title = 'Wastage Return List';

            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'purchase_invoice_no', name: 'purchase_invoice_no'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'customer-return-list') {
            var fileName = 'CustomerReturnList';
            var title = 'Customer Return List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'sale_invoice_no', name: 'sale_invoice_no'},
                {data: 'name', name: 'name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                }
            ];
        } else if (datatableType === 'income-expense-head-list') {
            var fileName = 'IncomeExpenseHeadList';
            var title = 'Income Expense Head List';
            serverSide = false;
        } else if (datatableType === 'income-list') {
            var fileName = 'IncomeList'; 
            var title = 'Income List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'date', name: 'date'},
                {data: 'invoice_no', name: 'invoice_no'},
                {data: 'details', name: 'details'},
                {data: 'income_amount', name: 'income_amount'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'expense-list') {
            var fileName = 'ExpenseList';
            var title = 'Expense List';
            serverSide = false;
        } else if (datatableType === 'designation-list') {
            var fileName = 'DesignationList';
            var title = 'Designation List';
            serverSide = false;
        } else if (datatableType === 'employee-list') {
            var fileName = 'EmployeeList';
            var title = 'Employee List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'name', name: 'name'},
                {data: 'designation_name', name: 'designation_name'},
                {data: 'email', name: 'email'},
                {data: 'mobile_no', name: 'mobile_no'},
                {data: 'phone', name: 'phone'},
                {data: 'blood_group', name: 'blood_group'},
                {data: 'salary', name: 'salary'},
                {data: 'status', name: 'status'},

                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'salary-list') {
            var fileName = 'SalaryList';
            var title = 'Salary List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'name', name: 'name'},
                {data: 'month_year', name: 'month_year'},
                {data: 'date', name: 'date'},
                {data: 'gross_salary', name: 'gross_salary'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'salary-payment-list') {
            var fileName = 'SalaryPaymentList';
            var title = 'Salary Payment List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'name', name: 'name'},
                {data: 'month_year', name: 'month_year'},
                {data: 'date', name: 'date'},
                {data: 'debit', name: 'debit'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                },
            ];
        } else if (datatableType === 'stock-report-list') {
            var fileName = 'StockReportList';
            var title = 'Stock Report List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'medicine_name', name: 'medicine_name'},
                {data: 'manufacturer_name', name: 'manufacturer_name'},
                {data: 'in_qty', name: 'in_qty'},
                {data: 'out_qty', name: 'out_qty'},
                {data: 'stock', name: 'stock'},
            ];
        } else if (datatableType === 'batch-wise-stock-report') {
            var fileName = 'BatchWiseStockReport';
            var title = 'Batch Wise Stock Report';
            serverSide = false;
        } else if (datatableType === 'due-list') { 
            var fileName = 'DueList';
            var title = 'Due List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'sale_invoice_no', name: 'sale_invoice_no'},
                {data: 'name', name: 'name'},
                {data: 'date', name: 'date'},
                {data: 'total_amount', name: 'total_amount'},
                {data: 'paid', name: 'paid'},
                {data: 'due', name: 'due'},
                {
                    data: 'action', 
                    name: 'action', 
                    orderable: true, 
                    searchable: true
                }
            ];
        } else if (datatableType === 'salary-report-list') {
            var fileName = 'SalaryReportList';
            var title = 'Salary Report List';
            columns = [
                {data: 'DT_RowIndex', name: 'DT_RowIndex'},
                {data: 'name', name: 'name'},
                {data: 'salary', name: 'salary'},
                {data: 'due', name: 'due'},
                {data: 'advanced', name: 'advanced'}
            ];
        } else if (datatableType === 'role-list') {
            var fileName = 'RoleList';
            var title = 'Role List';
            serverSide = false;
        } else if (datatableType === 'user-list') {
            var fileName = 'UserList';
            var title = 'User List';
            serverSide = false;
        } else if (datatableType === 'brand-list') {
            var fileName = 'BrandList';
            var title = 'Brand List';
            serverSide = false;
        } else if (datatableType === 'products-list') {
            var fileName = 'ProductList';
            var title = 'Product List';
            serverSide = false;
        } else if (datatableType === 'proposal-list') {
            var fileName = 'ProposalList';
            var title = 'Proposal List';
            serverSide = false;
        } else if (datatableType === 'service-type-list') {
            var fileName = 'ServiceTypeList';
            var title = 'Service Type List';
            serverSide = false;
        } else if (datatableType === 'supplier-list') {
            var fileName = 'SupplierList';
            var title = 'Supplier List';
            serverSide = false;
        } else if (datatableType === 'template-list') {
            var fileName = 'TemplateList';
            var title = 'Template List';
            serverSide = false;
        }

        var dtOptions = {
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            rowReorder: {
                selector: 'td:nth-child(2)'
            },
            ordering: true,
            processing: true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'copy',
                    title: title
                },
                {
                    extend: 'csv',
                    title: title
                },
                {
                    extend: 'excel',
                    fileName: fileName,
                    title: title
                },
                {
                    extend: 'pdf',
                    filename: fileName,
                    title: title
                },
            ]
        };

        // Enable server-side only if not explicitly disabled
        if (serverSide !== false && serverSide !== 'false') {
            dtOptions.serverSide = true;
            dtOptions.ajax = ajaxUrl;
            dtOptions.columns = columns;
        } else {
            dtOptions.serverSide = false;
            if (columns.length) {
                dtOptions.columns = columns;
            }
        }

        var table = $table.DataTable(dtOptions);

        // Append buttons to the correct container after table is drawn
        table.buttons().container().appendTo($table.closest('.dataTables_wrapper').find('.col-md-6:eq(0)'));
    });
});
