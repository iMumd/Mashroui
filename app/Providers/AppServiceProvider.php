<?php

namespace App\Providers;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Support\CurrentTerm;
use App\Support\Rbac\AccessControl;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTerm::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->role === RoleEnum::SuperAdmin ? true : null);

        Gate::define('module', fn (User $user, string $module) => app(AccessControl::class)->can($user, $module) !== AccessLevelEnum::Blocked);

        Gate::define('manage-org-structure', fn (User $user) => false);

        // المشرف (قيود فريقه) ولجنة الإشراف (صلاحيات مشرفيها) يصلون لبوابة القيود؛ السوبر أدمن عبر Gate::before.
        Gate::define('manage-restrictions', fn (User $user) => in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true));

        // تسجيل الدخول: سقف لكل (إيميل+IP) ضد التخمين على حساب واحد، وسقف أوسع لكل IP ضد تعداد الحسابات
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip|'.$request->ip()),
        ]);

        // نسيت كلمة المرور: يرسل بريدًا، فنقيّده بإحكام ضد إغراق صندوق الضحية وحرق كوتا SMTP
        RateLimiter::for('forgot-password', fn (Request $request) => [
            Limit::perMinute(1)->by('fp-email|'.strtolower((string) $request->input('email'))),
            Limit::perHour(5)->by('fp-email|'.strtolower((string) $request->input('email'))),
            Limit::perHour(10)->by('fp-ip|'.$request->ip()),
        ]);

        // إرسال بريد حرّ من الطاقم: سقف لكل مستخدم لمنع استخدامه للسبام
        RateLimiter::for('email-send', fn (Request $request) => [
            Limit::perMinute(10)->by('email-send|'.($request->user()?->id ?: $request->ip())),
            Limit::perHour(100)->by('email-send|'.($request->user()?->id ?: $request->ip())),
        ]);

        // الإرسال الجماعي (دعوات/بيانات اعتماد): كل استدعاء قد يبعث رسائل كثيرة، فنكبح عدد الاستدعاءات لكل مستخدم
        RateLimiter::for('bulk-notify', fn (Request $request) => Limit::perMinute(5)->by('bulk-notify|'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('invite-accept', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(15)->by($request->user()?->id ?: $request->ip()));
    }
}
