<?php

use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::view('user-guide', 'branding.user-guide')->middleware('auth')->name('statssa.guide')
    ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('branding.guide'), route('statssa.guide')));
Route::view('about/open-source', 'branding.open-source')->name('statssa.notices');
