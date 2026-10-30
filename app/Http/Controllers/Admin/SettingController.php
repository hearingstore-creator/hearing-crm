<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Configure middleware to handle authorization for different settings operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:setting.edit')->only(['create', 'update']);
    }

    /**
     * Show the form for creating a new setting entry.
     * If no setting exists, a default one is created.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $setting = Setting::where('id', 1)->first();
        if (!isset($setting)) {
            $obj = new Setting();
            $obj->company_name = 'Demo Company';
            $obj->save();
        }
        return view('admin.setting.edit', compact('setting'));
    }


    /**
     * Update the specified setting in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'company_name'   => ['required', 'string'],
                'mobile'         => ['required'],
                'website_url'    => ['nullable', 'string', 'max:255'],
                'partner_slogan' => ['nullable', 'string', 'max:255'],
                'logo'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
                'favicon'        => ['nullable', 'file', 'mimes:ico,png', 'max:512'],
            ]
        );

        $requestAll = $request->all();
        $uploadPath = 'file/setting/';

        if ($request->hasFile('logo')) {
            $requestAll['logo'] = Helper::fileUpload($request->file('logo'), $uploadPath);
        }
        if ($request->hasFile('favicon')) {
            $requestAll['favicon'] = Helper::fileUpload($request->file('favicon'), $uploadPath);
        }

        $setting = Setting::findOrFail($id);
        $setting->fill($requestAll);

        if ($setting->save()) {
            \App\Support\Region::forget();
            return redirect()->back()->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }
}
