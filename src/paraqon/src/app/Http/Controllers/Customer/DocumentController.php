<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Customer;

use App\Constants\Model\Status;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use StarsNet\Project\Paraqon\App\Models\Document;

class DocumentController extends Controller
{
    public function createDocument(Request $request)
    {
        $attributes = $request->all();
        $document = Document::create($attributes);

        return response()->json([
            'message' => 'Created new Document successfully',
            'id' => $document->id
        ]);
    }

    public function getAllDocuments(Request $request)
    {
        $queryParams = $request->query();

        $customer = $this->customer();

        $documentQuery = Document::where('customer_id', $customer->id)
            ->where('status', '!=', Status::DELETED);
        foreach ($queryParams as $key => $value) {
            $documentQuery->where($key, $value);
        }
        $documents = $documentQuery->latest()->get();

        return $documents;
    }

    public function getDocumentDetails(Request $request)
    {
        $documentID = $request->route('id');
        $document = Document::find($documentID);

        if (is_null($document)) {
            return response()->json([
                'message' => 'Document not found',
            ], 404);
        }

        return $document;
    }

    public function updateDocumentDetails(Request $request)
    {
        $documentID = $request->route('id');
        $document = Document::find($documentID);

        if (is_null($document)) {
            return response()->json([
                'message' => 'Document not found',
            ], 404);
        }

        $attributes = $request->all();
        $document->update($attributes);

        return response()->json([
            'message' => 'Updated Document successfully',
        ]);
    }
}
