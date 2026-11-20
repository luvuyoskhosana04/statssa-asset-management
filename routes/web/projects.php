<?php

use App\Http\Controllers\Projects\ProjectIssueController;
use App\Http\Controllers\Projects\ProjectsController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

Route::middleware('auth')->prefix('projects')->name('projects.')->group(function () {
    Route::get('/', [ProjectsController::class, 'index'])->name('index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('home')->push(trans('projects.title'), route('projects.index')));
    Route::get('create', [ProjectsController::class, 'create'])->name('create')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('projects.index')->push(trans('projects.create'), route('projects.create')));
    Route::post('/', [ProjectsController::class, 'store'])->name('store');
    Route::get('asset-issue', [ProjectIssueController::class, 'index'])->name('issue.index')
        ->breadcrumbs(fn (Trail $trail) => $trail->parent('projects.index')->push(trans('projects.issue'), route('projects.issue.index')));
    Route::post('asset-issue', [ProjectIssueController::class, 'start'])->name('issue.start');
    Route::get('asset-issue/{session}', [ProjectIssueController::class, 'show'])->whereUuid('session')->name('issue.show')
        ->breadcrumbs(fn (Trail $trail, string $session) => $trail->parent('projects.issue.index')->push(trans('projects.session'), route('projects.issue.show', $session)));
    Route::post('asset-issue/{session}/issue', [ProjectIssueController::class, 'store'])->whereUuid('session')->name('issue.store');
    Route::post('asset-issue/{session}/close', [ProjectIssueController::class, 'close'])->whereUuid('session')->name('issue.close');
    Route::get('asset-issue/{session}/{issue}/sign', [ProjectIssueController::class, 'resume'])->whereUuid('session')->whereNumber('issue')->name('issue.sign')
        ->breadcrumbs(fn (Trail $trail, string $session) => $trail->parent('projects.issue.show', $session)->push(trans('projects.signature')));
    Route::post('asset-issue/{session}/{issue}/handover', [ProjectIssueController::class, 'handover'])->whereUuid('session')->whereNumber('issue')->name('issue.handover');
    Route::post('asset-issue/{session}/{issue}/resolve', [ProjectIssueController::class, 'resolve'])->whereUuid('session')->whereNumber('issue')->name('issue.resolve');
    Route::get('{project}', [ProjectsController::class, 'show'])->whereNumber('project')->name('show')
        ->breadcrumbs(fn (Trail $trail, Project $project) => $trail->parent('projects.index')->push($project->name, route('projects.show', $project)));
    Route::get('{project}/edit', [ProjectsController::class, 'edit'])->whereNumber('project')->name('edit')
        ->breadcrumbs(fn (Trail $trail, Project $project) => $trail->parent('projects.show', $project)->push(trans('general.edit'), route('projects.edit', $project)));
    Route::put('{project}', [ProjectsController::class, 'update'])->whereNumber('project')->name('update');
    Route::post('{project}/members', [ProjectsController::class, 'member'])->whereNumber('project')->name('members.store');
    Route::delete('{project}/members/{user}', [ProjectsController::class, 'removeMember'])->whereNumber('project')->whereNumber('user')->name('members.remove');
    Route::post('{project}/reserve', [ProjectsController::class, 'reserve'])->whereNumber('project')->name('reserve');
    Route::delete('{project}/assets/{asset}', [ProjectsController::class, 'release'])->whereNumber('project')->whereNumber('asset')->name('release');
});
