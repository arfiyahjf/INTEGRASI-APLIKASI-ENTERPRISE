<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Loan;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;
use GuzzleHttp\Client;
use Carbon\Carbon;

/**
 * @OA\Info(
 *     title="Loan API",
 *     version="1.0.0"
 * )
 */
class LoanController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/loan/create",
     *     tags={"Loan"},
     *     summary="Create a new loan (borrow book)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "book_id", "borrowed_at", "due_date", "status"},
     *             @OA\Property(property="user_id", type="string"),
     *             @OA\Property(property="book_id", type="string"),
     *             @OA\Property(property="borrowed_at", type="string", format="date"),
     *             @OA\Property(property="due_date", type="string", format="date"),
     *             @OA\Property(property="status", type="string", enum={"borrowed", "returned"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Loan created successfully"),
     *     @OA\Response(response=400, description="Invalid book ID"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'book_id' => 'required|string',
            'borrowed_at' => 'required|date',
            'due_date' => 'required|date|after_or_equal:borrowed_at',
            'status' => 'required|string|in:borrowed,returned',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $isBookValid = $this->checkBookId($request->book_id);

        if (!$isBookValid) {
            return response()->json([
                'message' => 'Invalid book ID'
            ], 400);
        }

        $loan = Loan::create([
            'user_id' => $request->user_id,
            'book_id' => $request->book_id,
            'borrowed_at' => Carbon::parse($request->borrowed_at),
            'due_date' => Carbon::parse($request->due_date),
            'status' => $request->status,
        ]);

        $this->decrementBookAvailability($request->book_id);

        return response()->json([
            'message' => 'Loan created successfully',
            'loan' => $loan
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/loans/return/{id}",
     *     tags={"Loan"},
     *     summary="Return a borrowed book",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Book returned successfully"),
     *     @OA\Response(response=400, description="Book already returned or not borrowed"),
     *     @OA\Response(response=404, description="Loan not found")
     * )
     */
    public function returnBook($id)
    {
        $loan = Loan::find($id);

        if (!$loan) {
            return response()->json([
                'message' => 'Loan not found'
            ], 404);
        }

        if ($loan->status !== 'borrowed') {
            return response()->json([
                'message' => 'Book has already been returned or is not borrowed'
            ], 400);
        }

        $loan->returned_at = Carbon::now();
        $loan->status = 'returned';
        $loan->save();

        $this->incrementBookAvailability($loan->book_id);

        return response()->json([
            'message' => 'Book returned successfully',
            'loan' => $loan
        ], 200);
    }

    private function checkBookId($bookId)
    {
        try {
            $client = new Client();
            $response = $client->get("http://localhost:8001/api/book/{$bookId}");

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function decrementBookAvailability($bookId)
    {
        try {
            $client = new Client();
            $client->post("http://localhost:8001/api/book/decrement/{$bookId}");
        } catch (\Exception $e) {
            // fail silently
        }
    }

    private function incrementBookAvailability($bookId)
    {
        try {
            $client = new Client();
            $client->post("http://localhost:8001/api/book/increment/{$bookId}");
        } catch (\Exception $e) {
            // fail silently
        }
    }
}
