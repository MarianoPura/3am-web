<?php
declare(strict_types=1);
namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\RentalMailSettings;
use App\Services\RentalAccount;
use App\Services\RentalCart;
use App\Services\RentalMailEvents;
use App\Services\RentalNotification;

/** Isolated mailer settings; every action authorizes Admin/Superadmin server-side. */
final class RentalEmailController extends Controller
{
    private ?array $admin = null;
    private function deny(): ?Response
    {
        $this->admin = (new RentalAccount($this->db(), new RentalCart()))->current();
        if (!$this->admin) { return $this->redirect(url('rentals/account')); }
        return in_array(strtolower($this->admin['role']), ['admin','superadmin'], true)
            ? null : $this->render('rentals.forbidden', [], 403)->noCache();
    }
    private function settings(): RentalMailSettings { return new RentalMailSettings($this->db()); }
    private function mailer(): RentalNotification { return $this->container->get(RentalNotification::class); }
    private function page(string $view, array $data = [], int $status = 200): Response
    {
        $notice = $_SESSION['rentals_mail_notice'] ?? null;
        unset($_SESSION['rentals_mail_notice']);
        return $this->render('rentals.admin.email-' . $view, $data + ['section'=>'email','adminUser'=>$this->admin,
            'notice'=>$notice,'events'=>RentalMailEvents::labels(),'mailerStatus'=>$this->mailer()->configurationStatus()], $status)->noCache();
    }
    private function unavailable(): Response
    {
        error_log('Rentals email settings unavailable; check additive mailer schema.');
        return $this->page('templates', ['templates'=>[], 'error'=>'Email settings are unavailable. Have the server administrator verify the additive mailer schema.'], 503);
    }
    public function index(Request $request): Response
    {
        if ($r=$this->deny()) { return $r; }
        try { return $this->page('templates', ['templates'=>$this->settings()->templates()]); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function edit(Request $request, string $event, string $audience): Response
    {
        if ($r=$this->deny()) { return $r; }
        if (!isset(RentalMailEvents::labels()[$event]) || !in_array($audience,['customer','admin'],true)) { return Response::notFound(); }
        try { return $this->page('template-edit', ['template'=>$this->settings()->template($event,$audience)]); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function update(Request $request, string $event, string $audience): Response
    {
        if ($r=$this->deny()) { return $r; }
        if (!isset(RentalMailEvents::labels()[$event]) || !in_array($audience,['customer','admin'],true)) { return Response::notFound(); }
        $draft = ['event_key'=>$event,'audience'=>$audience,'display_name'=>$request->string('display_name'),
            'subject'=>$request->string('subject'),'body'=>$request->string('body'),'is_active'=>$request->string('is_active')==='1'?1:0];
        try {
            $action=$request->string('action','save');
            if ($action==='preview') { return $this->page('template-edit',['template'=>$draft,'preview'=>$this->mailer()->preview($draft)]); }
            if ($action==='reset') { $draft=RentalMailEvents::defaults($event,$audience); }
            elseif (!in_array($action,['save','test'],true)) { throw new \InvalidArgumentException('Choose a valid template action.'); }
            if ($action==='test') {
                $this->mailer()->sendTest($draft,$request->string('test_email'));
                return $this->page('template-edit',['template'=>$draft,'success'=>'Test email sent. Sample data only; the template and orders were not changed.']);
            }
            $this->settings()->saveTemplate($draft);
            $_SESSION['rentals_mail_notice']='Template saved.';
            return $this->redirect(url('rentals/admin/email/templates/'.$event.'/'.$audience));
        } catch (\InvalidArgumentException $e) { return $this->page('template-edit',['template'=>$draft,'error'=>$e->getMessage()],422); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function recipients(Request $request): Response
    {
        if ($r=$this->deny()) { return $r; }
        try { return $this->page('recipients',['recipients'=>$this->settings()->recipients(),'editId'=>max(0,$request->int('edit'))]); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function saveRecipient(Request $request): Response
    {
        if ($r=$this->deny()) { return $r; }
        $draft=['id'=>max(0,$request->int('id')),'name'=>$request->string('name'),'email'=>$request->string('email'),
            'is_active'=>$request->string('is_active')==='1'?1:0,'events'=>$request->array('events')];
        try {
            $this->settings()->saveRecipient($draft['id'],$draft['name'],$draft['email'],(bool)$draft['is_active'],$draft['events']);
            $_SESSION['rentals_mail_notice']='Recipient and subscriptions saved.';
            return $this->redirect(url('rentals/admin/email/recipients'));
        } catch (\InvalidArgumentException $e) { return $this->page('recipients',['recipients'=>$this->settings()->recipients(),'draft'=>$draft,'error'=>$e->getMessage()],422); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function removeRecipient(Request $request, string $id): Response
    {
        if ($r=$this->deny()) { return $r; }
        if ($request->string('confirm')!=='remove') { return Response::make('Confirm recipient removal.',422)->noCache(); }
        try { $this->settings()->removeRecipient((int)$id); $_SESSION['rentals_mail_notice']='Recipient removed. Delivery history is preserved.'; }
        catch (\Throwable $e) { return $this->unavailable(); }
        return $this->redirect(url('rentals/admin/email/recipients'));
    }
    public function history(Request $request): Response
    {
        if ($r=$this->deny()) { return $r; }
        try { return $this->page('history',['deliveries'=>$this->settings()->history($request->int('page',1)),'page'=>max(1,$request->int('page',1))]); }
        catch (\Throwable $e) { return $this->unavailable(); }
    }
    public function retry(Request $request, string $id): Response
    {
        if ($r=$this->deny()) { return $r; }
        try { $this->mailer()->retry((int)$id); $_SESSION['rentals_mail_notice']='Retry processed. See the delivery result below.'; }
        catch (\InvalidArgumentException $e) { $_SESSION['rentals_mail_notice']=$e->getMessage(); }
        catch (\Throwable $e) { $_SESSION['rentals_mail_notice']='Retry could not be processed. Check mailer configuration and schema.'; }
        return $this->redirect(url('rentals/admin/email/history'));
    }
}
