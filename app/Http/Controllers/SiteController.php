<?php

namespace App\Http\Controllers;

use App\Models\Faq;

/** Public pages linked from every footer: FAQ, terms of use and cookie policy. */
class SiteController extends Controller
{
    public function faq()
    {
        return view('site.faq', ['faqs' => Faq::published(app()->getLocale())->get()]);
    }

    public function terms()
    {
        return view('site.terms');
    }

    public function cookies()
    {
        return view('site.cookies');
    }
}
