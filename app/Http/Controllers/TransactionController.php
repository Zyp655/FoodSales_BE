<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Order; // Phải thêm Order model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
   
    public function generateQrTransaction(Request $request)
    {
        $userId = $request->user()->id;
        
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:order,id',
            'amount' => 'required|numeric|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Validation error.', 'errors' => $validator->errors()], 422);
        }
        
        $orderId = $request->order_id;
        $requestedAmount = $request->amount;
        
        $order = Order::find($orderId);

        if (!$order || $order->user_id !== $userId) {
            return response()->json(['success' => 0, 'message' => 'Order not found or access denied.'], 404);
        }
        
        if (floatval($requestedAmount) !== floatval($order->total_amount)) {
            return response()->json(['success' => 0, 'message' => 'The payment amount does not match the order total amount.'], 400);
        }

        $existingTransaction = Transaction::where('order_id', $orderId)
            ->where('status', 'PENDING')
            ->first();
        
        if ($existingTransaction) {
             return response()->json([
                'success' => 1,
                'message' => 'A pending transaction already exists. QR data returned.',
                'transaction_id' => $existingTransaction->id,
                'qr_data' => $existingTransaction->qr_data,
                'amount_to_pay' => $existingTransaction->amount
            ], 200);
        }
        
        $order->update(['status' => 'Payment_Pending']);

        $bankAccountNumber = env('BANK_STK');
        $bankId = env('BANK_ID', '9704XX');
        $transactionNote = "TTDH{$orderId}US{$userId}";

        $qrString = "bankId={$bankId}&accNo={$bankAccountNumber}&amount={$requestedAmount}&memo={$transactionNote}";
        
        $transaction = Transaction::create([
            'order_id' => $orderId,
            'user_id' => $userId,
            'amount' => $requestedAmount,
            'payment_method' => 'QR_BANK',
            'status' => 'PENDING',
            'qr_data' => $qrString,
        ]);

        return response()->json([
            'success' => 1,
            'message' => 'Transaction created. QR data generated.',
            'transaction_id' => $transaction->id,
            'qr_data' => $qrString,
            'amount_to_pay' => $requestedAmount
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
        $newStatus = $request->status;

        if ($transaction->status === $newStatus) {
             return response()->json(['success' => 1, 'message' => 'Status remains unchanged.'], 200);
        }
        
        $transaction->update([
            'status' => $newStatus,
            'completed_at' => ($newStatus === 'COMPLETED' || $newStatus === 'FAILED') ? now() : null,
        ]);

        $order = Order::find($transaction->order_id);
        
        if ($order) {
            if ($newStatus === 'COMPLETED') {
                $order->update(['status' => 'Processing']);
            } elseif ($newStatus === 'FAILED') {
                $order->update(['status' => 'Payment_Failed']);
            }
        }
        
        return response()->json(['success' => 1, 'message' => 'Transaction status updated and Order status synchronized.']);
    }
}