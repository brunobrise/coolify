<?php

namespace App\Http\Controllers;

use App\Events\TestEvent;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function realtime_test()
    {
        if (auth()->user()?->currentTeam()->id !== 0) {
            return redirect(RouteServiceProvider::HOME);
        }
        TestEvent::dispatch();

        return 'Look at your other tab.';
    }

    public function verify()
    {
        return view('auth.verify-email');
    }

    public function email_verify(Request $request)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $user = auth()->user();
        if (! $user) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), hash('sha256', $user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect(RouteServiceProvider::HOME);
    }

    public function forgot_password(Request $request)
    {
        if (is_transactional_emails_enabled()) {
            $arrayOfRequest = $request->only(Fortify::email());
            $request->merge([
                'email' => Str::lower($arrayOfRequest['email']),
            ]);
            $type = set_transanctional_email_settings();
            if (blank($type)) {
                return response()->json(['message' => 'Transactional emails are not active'], 400);
            }
            $request->validate([Fortify::email() => 'required|email']);
            $status = Password::broker(config('fortify.passwords'))->sendResetLink(
                $request->only(Fortify::email())
            );
            if ($status == Password::RESET_LINK_SENT) {
                return app(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => $status]);
            }
            if ($status == Password::RESET_THROTTLED) {
                return response('Already requested a password reset in the past minutes.', 400);
            }

            return app(FailedPasswordResetLinkRequestResponse::class, ['status' => $status]);
        }

        return response()->json(['message' => 'Transactional emails are not active'], 400);
    }

    public function link()
    {
        $token = request()->get('token');
        if ($token) {
            try {
                $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return redirect()->route('login')->with('error', 'Invalid credentials.');
            }

            $email = Str::lower((string) data_get($payload, 'email'));
            $password = (string) data_get($payload, 'password');
            $invitationUuid = (string) data_get($payload, 'invitation_uuid');
            $expiresAt = (int) data_get($payload, 'expires_at', 0);

            if (blank($email) || blank($password) || $expiresAt < now()->timestamp) {
                return redirect()->route('login')->with('error', 'Invalid credentials.');
            }

            $user = User::whereEmail($email)->first();
            if (! $user) {
                return redirect()->route('login');
            }
            $invitation = $this->resolveInviteLoginInvitation($email, $invitationUuid);
            if (! $invitation || ! $invitation->isValid()) {
                return redirect()->route('login')->with('error', 'Invalid credentials.');
            }
            if (Hash::check($password, $user->password)) {
                $team = $invitation->team;
                $user->teams()->syncWithoutDetaching([$team->id => ['role' => $invitation->role]]);
                $invitation->delete();
                Auth::login($user);
                session(['currentTeam' => $team]);

                return redirect()->route('dashboard');
            }
        }

        return redirect()->route('login')->with('error', 'Invalid credentials.');
    }

    private function resolveInviteLoginInvitation(string $email, string $invitationUuid): ?TeamInvitation
    {
        $query = TeamInvitation::whereEmail($email);

        if (filled($invitationUuid)) {
            $query->whereUuid($invitationUuid);
        }

        return $query->first();
    }

    public function showInvitation()
    {
        $invitationUuid = request()->route('uuid');
        $invitation = TeamInvitation::whereUuid($invitationUuid)->firstOrFail();
        $user = User::whereEmail($invitation->email)->firstOrFail();

        if (Auth::id() !== $user->id) {
            abort(400, 'You are not allowed to accept this invitation.');
        }

        if (! $invitation->isValid()) {
            abort(400, 'Invitation expired.');
        }

        $alreadyMember = $user->teams()->where('team_id', $invitation->team->id)->exists();

        return view('invitation.accept', [
            'invitation' => $invitation,
            'team' => $invitation->team,
            'alreadyMember' => $alreadyMember,
        ]);
    }

    public function acceptInvitation()
    {
        $invitationUuid = request()->route('uuid');

        $invitation = TeamInvitation::whereUuid($invitationUuid)->firstOrFail();
        $user = User::whereEmail($invitation->email)->firstOrFail();

        if (Auth::id() !== $user->id) {
            abort(400, 'You are not allowed to accept this invitation.');
        }

        if (! $invitation->isValid()) {
            abort(400, 'Invitation expired.');
        }

        if ($user->teams()->where('team_id', $invitation->team->id)->exists()) {
            $invitation->delete();

            return redirect()->route('team.index');
        }
        $user->teams()->attach($invitation->team->id, ['role' => $invitation->role]);
        $invitation->delete();

        refreshSession($invitation->team);

        return redirect()->route('team.index');
    }
}
