<?php
declare(strict_types=1);
namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\{RentalPasswordReset,RentalMailTransport,RentalAuthThrottle};

final class RentalPasswordController extends Controller
{
    private function recovery(): RentalPasswordReset { return new RentalPasswordReset($this->db(),$this->container->get(RentalMailTransport::class)); }

    public function forgot(Request $request): Response
    {
        $error=null; $notice=null;
        if ($request->isPost()) {
            try {
                if (!(new RentalAuthThrottle())->attempt('reset',$request->ip(),$request->string('email'))) {
                    return $this->render('rentals.password',['error'=>'Too many requests. Please try again in an hour.','notice'=>null,'token'=>null],429)->noCache()->withHeader('Retry-After','3600');
                }
                $this->recovery()->request($request->string('email'));
                $notice='If an account matches that email, a password-reset link will be sent. Check your inbox and spam folder.';
            } catch (\RuntimeException $e) { $error=$e->getMessage(); }
            catch (\Throwable $e) { error_log('Rentals password recovery unavailable'); $error='Password recovery is unavailable right now. Please try again later.'; }
        }
        return $this->render('rentals.password',compact('error','notice')+['token'=>null],$error?422:200)->noCache();
    }

    public function reset(Request $request,string $token): Response
    {
        $error=null; $notice=null; $valid=false;
        try {
            $valid=$this->recovery()->valid($token);
            if (!$valid) { $error='This reset link is invalid or expired. Request a new one.'; }
            elseif ($request->isPost()) {
                $this->recovery()->reset($token,$request->string('password'),$request->string('password_confirmation'));
                $notice='Password updated. Sign in with your new password.';
                unset($_SESSION['user_id'],$_SESSION['rentals_authenticated_at']);
            }
        } catch (\RuntimeException $e) { $error=$e->getMessage(); }
        catch (\Throwable $e) { error_log('Rentals password reset unavailable'); $error='Your password could not be updated. Please try again later.'; }
        return $this->render('rentals.password',compact('error','notice','token','valid'),$error?422:200)->noCache();
    }
}
