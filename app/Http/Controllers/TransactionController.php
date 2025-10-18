<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    public function generateQrTransaction(Request $request)
    {
        $userId = $request->user()->id; 
        
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'amount' => 'required|numeric|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }
        
        $orderId = $request->order_id;
        $amount = $request->amount;
        
        $bankAccountNumber = env('BANK_STK'); 
        $bankId = env('BANK_ID', '9704XX'); 
        $transactionNote = "TTDH{$orderId}US{$userId}";

        $qrString = "bankId={$bankId}&accNo={$bankAccountNumber}&amount={$amount}&memo={$transactionNote}";
        
        $transaction = Transaction::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'amount' => $amount,
            'payment_method' => 'QR_BANK',
            'status' => 'PENDING',
            'qr_data' => $qrString,
        ]);

        return response()->json([
            'success' => 1,
            'message' => 'Transaction created. QR data generated.',
            'transaction_id' => $transaction->id,
            'qr_data' => $qrString, 
            'amount_to_pay' => $amount
        ], 201);
    }
    public function updateStatus(Request $request, $transactionId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:PENDING,COMPLETED,FAILED',
        ]);
        
        if ($validator->fails()) { 
            return response()->json(['success' => 0, 'message' => 'Invalid status value provided.', 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($transactionId);
        
        $transaction->update([
            'status' => $request->status,
            'completed_at' => now(),
        ]);
        
        return response()->json(['success' => 1, 'message' => 'Status updated.']);
    }
}