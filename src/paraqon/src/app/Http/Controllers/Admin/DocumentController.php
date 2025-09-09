<?php

namespace StarsNet\Project\Paraqon\App\Http\Controllers\Admin;

// Laravel built-in
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use StarsNet\Project\Paraqon\App\Models\Document;

// Constants
use App\Constants\Model\Status;

class DocumentController extends Controller
{
    public function createDocument(Request $request)
    {
        $attributes = $request->all();
        $document = Document::create($attributes);

        return response()->json([
            'message' => 'Created new Document successfully',
            '_id' => $document->id
        ]);
    }

    public function getAllDocuments(Request $request)
    {
        $queryParams = $request->query();

        $documentQuery = Document::where('status', '!=', Status::DELETED);

        foreach ($queryParams as $key => $value) {
            if (in_array($key, ['per_page', 'page', 'sort_by', 'sort_order'])) {
                continue;
            }

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
