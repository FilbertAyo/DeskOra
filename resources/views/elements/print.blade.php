 <div class="modal fade" id="pettyCashModal" tabindex="-1" aria-labelledby="pcvModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-lg modal-dialog-scrollable">
         <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pcvModalLabel"><button class="btn btn-secondary" onclick="printPCV()"><i
                            class="ti ti-printer"></i></button></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
             <div class="modal-body">
                 <div id="printable-pcv">

                     <div class="pcv-container">

                         @if ($request->status == 'paid' || $request->status == 'approved')
                             <div class="status-stamp paid">{{ strtoupper($request->status) }}
                             </div>
                         @else
                             <div class="status-stamp not-paid">{{ strtoupper($request->status) }}</div>
                         @endif


                         <div class="pcv-header text-center">
                             <img src="{{ asset('image/longl.png') }}" alt="" class="mb-2"
                                 style="height: 60px;"><br>
                             P.O. BOX 20226, DSM, TANZANIA
                             TEL.: 022 2124760 | FAX: 2124759
                             Email: info@marstanzania.com
                         </div>

                         <div class="pcv-title">PETTY CASH VOUCHER</div>

                         <div class="pcv-section pcv-flex pcv-space">
                             <div><span class="pcv-label">PCV No:</span>
                                 #{{ str_pad($request->id, 3, '0', STR_PAD_LEFT) }}
                             </div>
                             <div><span class="pcv-label">Date:</span>
                                 {{ \Carbon\Carbon::parse($request->created_at)->format('d/m/Y') }}</div>
                             <div class="pcv-label">
                                 <span class="pcv-label">Department:</span> {{ $request->department->name ?? 'N/A' }}
                             </div>
                         </div>

                         <table class="pcv-table">
                             <thead>
                                 <tr>
                                     <th>DESCRIPTION</th>
                                     <th>AMOUNT</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <tr>
                                     <td>{{ $request->reason }}</td>
                                     <td>{{ number_format($request->amount, 2) }}</td>
                                 </tr>
                                 <tr>
                                     <td><strong>TOTAL: {{ $amountInWords }}</strong></td>
                                     <td><strong>{{ number_format($request->amount, 2) }}</strong></td>
                                 </tr>
                             </tbody>
                         </table>

                         <div class="signature-row"
                             style="display: flex; justify-content: space-between; text-align: center; margin-top: 10px;">
                             <!-- Raised By -->
                             <div class="signature-block" style="flex: 1;">
                                 <div>Raised by:</div>
                                 <div
                                     style="margin: 10px 0 5px; border-bottom: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto;">
                                     @if (!empty($request->user->signature))
                                         <img src="{{ asset($request->user->signature) }}" alt="Signature"
                                             style="max-height: 60px;">
                                     @endif
                                 </div>
                                 <div> {{ $request->user->name }}</div>
                                 <div>{{ $request->created_at->format('d/m/Y') }}</div>
                             </div>

                             <!-- Verified By -->
                             <div class="signature-block" style="flex: 1;">
                                 <div>Verified by:</div>
                                 <div
                                     style="margin: 10px 0 5px; border-bottom: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto;">
                                     @if (!empty($verifiedBy->user->signature))
                                         <img src="{{ asset($verifiedBy->user->signature) }}" alt="Signature"
                                             style="max-height: 60px;">
                                     @endif
                                 </div>
                                 <div>{{ $verifiedBy->user->name ?? '' }}</div>
                                 @if (!empty($verifiedBy))
                                     <div>{{ $verifiedBy->created_at->format('d/m/Y') }}</div>
                                 @endif

                             </div>

                             <!-- Approved By -->
                             <div class="signature-block" style="flex: 1;">
                                 <div>Approved by:</div>
                                 <div
                                     style="margin: 10px 0 5px; border-bottom: 1px solid #000; width: 80%; margin-left: auto; margin-right: auto;">
                                     @if ($approvedBy && !empty($gm->signature))
                                         <img src="{{ asset($gm->signature) }}" alt="Signature"
                                             style="max-height: 60px;">
                                     @endif
                                 </div>
                                 <div>GM</div>
                                 @if ($approvedBy)
                                     <div>{{ $approvedBy->created_at->format('d/m/Y') }}</div>
                                 @endif
                             </div>
                         </div>

                     </div>

                 </div>
             </div>
         </div>
     </div>
 </div>


 <style>
     .pcv-container {
         position: relative;
         font-family: Arial, sans-serif;
         padding: 20px;
         border: 1px solid #ccc;
         background: #fff;
         font-size: 14px;
     }

     .pcv-header {
         text-align: right;
         font-size: 13px;
     }

     .pcv-title {
         text-align: center;
         font-weight: bold;
         font-size: 16px;
         margin: 10px 0;
     }


     .pcv-flex {
         display: flex;
     }

     .pcv-space {
         justify-content: space-between;
     }

     .pcv-label {
         font-weight: bold;
         margin-right: 10px;
     }

     .pcv-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 15px;
     }

     .pcv-table,
     .pcv-table th,
     .pcv-table td {
         border: 1px solid #000;
     }

     .pcv-table th,
     .pcv-table td {
         padding: 8px;
         text-align: left;
     }

     .status-stamp {
         position: absolute;
         top: 40%;
         left: 50%;
         transform: translate(-50%, -50%) rotate(-25deg);
         font-size: 80px;
         font-weight: bold;
         opacity: 0.15;
         z-index: 0;
         white-space: nowrap;
         pointer-events: none;
         user-select: none;
     }

     .status-stamp.paid {
         color: green;
     }

     .status-stamp.not-paid {
         color: red;
     }
 </style>


 <script>
     const styles = `
<style>
    .pcv-container {
        position: relative;
        font-family: Arial, sans-serif;
        padding: 20px;
        border: 1px solid #ccc;
        background: #fff;
        font-size: 14px;
    }
    .pcv-header {
        text-align: right;
        font-size: 13px;
    }
    .pcv-title {
        text-align: center;
        font-weight: bold;
        font-size: 16px;
        margin: 10px 0;
    }
    .pcv-flex {
        display: flex;
    }
    .pcv-space {
        justify-content: space-between;
    }
    .pcv-label {
        font-weight: bold;
        margin-right: 10px;
    }
    .pcv-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .pcv-table,
    .pcv-table th,
    .pcv-table td {
        border: 1px solid #000;
    }
    .pcv-table th,
    .pcv-table td {
        padding: 8px;
        text-align: left;
    }
    .status-stamp {
        position: absolute;
        top: 40%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-25deg);
        font-size: 80px;
        font-weight: bold;
        opacity: 0.15;
        z-index: 0;
        white-space: nowrap;
        pointer-events: none;
        user-select: none;
    }
    .status-stamp.paid {
        color: green;
    }
    .status-stamp.not-paid {
        color: red;
    }
</style>
`;

     function printPCV() {
         const content = document.getElementById('printable-pcv').innerHTML;
         const printWindow = window.open('', '', 'width=800,height=600');

         printWindow.document.write(`
<html>
    <head>
        <title>Print PCV</title>
        ${styles}
    </head>
    <body>
        ${content}
        <script>
            window.onload = function() {
                window.focus();
                window.print();
                window.onafterprint = function () {
                    window.close();
                };
            };
        <\/script>
    </body>
</html>
`);


         printWindow.document.close();
     }
 </script>
