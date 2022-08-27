<?php

use App\Models\User;
use Inertia\Inertia;
use App\Jobs\TestJob;

use App\Events\TestEvent;
    
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\VideoChatController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/video/chat', function(){

    $users = User::where('id', '<>', Auth::id())->get();

    return Inertia::render('Video', ['allusers' => $users]);

})->middleware(['auth', 'verified'])->name('video.chat');

// Route::get('/fire', function() {

//     // TestEvent::dispatch(Auth::user(), 'messagtresdfgh');
//     event(new TestEvent('God abeg'));
//     // broadcast(new TestEvent('God abeg 00'));
// });


// Route::get('/video-chat', function () {
//     // fetch all users apart from the authenticated user
//     $users = User::where('id', '<>', Auth::id())->get();
//     return view('video-chat', ['users' => $users]);
// });

// Endpoints to call or receive calls.
Route::post('/video/call-user', [VideoChatController::class, 'callUser']);
Route::post('/video/accept-call', [VideoChatController::class, 'acceptCall']);

require __DIR__.'/auth.php';
