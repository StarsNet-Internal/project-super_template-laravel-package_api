<?php

namespace StarsNet\Project\Ops\App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Models\Account;
use StarsNet\Project\Ops\App\Models\DashboardTemplate;

class TemplateController extends Controller
{
    public function getAllTemplates(Request $request)
    {
        $account = $this->account();

        $templates = DashboardTemplate::where('account_ids', 'all', [$account->_id])
            ->where('status', 'ACTIVE')
            ->get();

        return $templates;
    }

    public function deleteTemplates(Request $request)
    {
        // Extract attributes from $request
        $templateIds = $request->input('ids', []);

        // Get Template(s)
        /** @var Collection $templates */
        $templates = DashboardTemplate::find($templateIds);

        // Update Template(s)
        /** @var DashboardTemplate $template */
        foreach ($templates as $template) {
            $template->statusDeletes();
        }

        // Return success message
        return response()->json([
            'message' => 'Deleted ' . $templates->count() . ' Template(s) successfully'
        ], 200);
    }

    public function createTemplate(Request $request)
    {
        $account = $this->account();

        $template = DashboardTemplate::create($request->all());
        $template->attachAccounts(collect([$account]));

        foreach ($request->input('components', []) as $component) {
            $template->createComponent($component);
        }

        return response()->json([
            'message' => 'Created New Template successfully',
            '_id' => $template->_id
        ], 200);
    }

    public function getTemplateDetails(Request $request)
    {
        // Extract attributes from $request
        $templateId = $request->route('id');

        // Get Template, then validate
        /** @var DashboardTemplate $template */
        $template = DashboardTemplate::find($templateId);

        if (is_null($template)) {
            return response()->json([
                'message' => 'Template not found'
            ], 404);
        }

        // Return Template
        return response()->json($template, 200);
    }

    public function updateTemplateDetails(Request $request)
    {
        // Extract attributes from $request
        $templateId = $request->route('id');
        $attributes = $request->all();

        // Get Template, then validate
        /** @var DashboardTemplate $template */
        $template = DashboardTemplate::find($templateId);

        if (is_null($template)) {
            return response()->json([
                'message' => 'Template not found'
            ], 404);
        }

        // Update Template
        $template->update($attributes);

        // Return success message
        return response()->json([
            'message' => 'Updated Template successfully',
        ], 200);
    }

    public function getAllAdminTemplates(Request $request)
    {
        $account = Account::where('user_id', 1)->first();

        $templates = DashboardTemplate::where('account_ids', 'all', [$account->_id])
            ->where('status', 'ACTIVE')
            ->get();

        return $templates;
    }
}
