<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentReportController;
use App\Http\Controllers\AlertActionController;
use App\Http\Controllers\AlertGroupController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ComplianceController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\ExplorerController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PdfReportController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\ThreatController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/setup', [SetupController::class, 'show'])->name('setup');
Route::post('/setup/database', [SetupController::class, 'storeDatabase'])->name('setup.database.store');
Route::post('/setup/database/test', [SetupController::class, 'testDatabase'])->name('setup.database.test');
Route::post('/setup/connection', [SetupController::class, 'storeConnection'])->name('setup.connection.store');
Route::post('/setup/connection/test', [SetupController::class, 'testConnection'])->name('setup.connection.test');
Route::post('/setup/account', [SetupController::class, 'storeAdmin'])->name('setup.account.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');

    Route::get('/', [OverviewController::class, 'index'])->name('overview');
    Route::get('/overview/data', [OverviewController::class, 'data'])->name('overview.data');

    Route::get('/explorer', [ExplorerController::class, 'index'])->name('explorer.index');
    Route::get('/explorer/alert', [ExplorerController::class, 'detail'])->name('explorer.detail');
    Route::get('/explorer/report', [PdfReportController::class, 'alerts'])->name('explorer.report');

    // ---- Reports: one queue for incidents, vulnerabilities and SCA ----------
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportsController::class, 'show'])->name('reports.show');

    Route::middleware('role:admin,analyst')->group(function () {
        Route::post('/alerts/update', [AlertActionController::class, 'update'])->name('alerts.update');
        Route::post('/alerts/bulk', [AlertActionController::class, 'bulk'])->name('alerts.bulk');
        Route::post('/alerts/{annotation}/comment', [AlertActionController::class, 'comment'])->name('alerts.comment');

        Route::post('/reports/from-alert', [ReportsController::class, 'storeFromAlert'])->name('reports.from-alert');
        Route::post('/reports/from-group', [AlertGroupController::class, 'store'])->name('reports.from-group');
        Route::post('/reports/from-vulnerability', [ReportsController::class, 'storeFromVulnerability'])->name('reports.from-vulnerability');
        Route::post('/reports/from-sca', [ReportsController::class, 'storeFromSca'])->name('reports.from-sca');
        Route::put('/reports/{report}', [ReportsController::class, 'update'])->name('reports.update');
        Route::post('/reports/{report}/status', [ReportsController::class, 'changeStatus'])->name('reports.status');
        Route::post('/reports/{report}/verify', [ReportsController::class, 'verify'])->name('reports.verify');
        Route::post('/reports/{report}/refresh', [ReportsController::class, 'refresh'])->name('reports.refresh');
        Route::post('/reports/{report}/note', [ReportsController::class, 'addNote'])->name('reports.note');
    });

    Route::get('/threats', [ThreatController::class, 'index'])->name('threats.index');

Route::get('/alert-groups', [AlertGroupController::class, 'index'])->name('alert-groups.index');
Route::get('/alert-groups/alerts', [AlertGroupController::class, 'alerts'])->name('alert-groups.alerts');

    Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
    Route::get('/agents/{id}', [AgentController::class, 'show'])->name('agents.show');
    Route::post('/agents/{id}/kb-titles', [AgentController::class, 'kbTitles'])
        ->middleware(['role:admin,analyst', 'throttle:60,1'])
        ->name('agents.kb-titles');
    Route::get('/agents/{id}/vulnerabilities', [AgentController::class, 'vulnerabilities'])->name('agents.vulnerabilities');
    Route::get('/agents/{id}/sca', [AgentController::class, 'sca'])->name('agents.sca');
    Route::get('/vulnerabilities/cve/{cve}', [AgentController::class, 'cveDetail'])->name('vulnerabilities.cve');

    // ---- Agent-scoped PDF exports -----------------------------------------
    Route::get('/agents/{id}/report', [AgentReportController::class, 'agent'])->name('agents.report');
    Route::get('/agents/{id}/vulnerabilities/report', [AgentReportController::class, 'vulnerabilities'])->name('agents.vulnerabilities.report');
    Route::get('/agents/{id}/sca/report', [AgentReportController::class, 'sca'])->name('agents.sca.report');


    Route::get('/compliance', [ComplianceController::class, 'index'])->name('compliance.index');

    Route::middleware('can:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

        Route::get('/config', [ConfigController::class, 'index'])->name('config.index');
        Route::put('/config', [ConfigController::class, 'update'])->name('config.update');
        Route::post('/config/test', [ConfigController::class, 'test'])->name('config.test');
        Route::delete('/config', [ConfigController::class, 'reset'])->name('config.reset');
    });

    // ---- Legacy URLs keep working ------------------------------------------
    Route::redirect('/tickets', '/reports');
    Route::redirect('/incidents', '/reports');
    Route::get('/tickets/{any}', fn () => redirect()->route('reports.index'))->where('any', '.*');
    Route::get('/incidents/{any}', fn () => redirect()->route('reports.index'))->where('any', '.*');
});