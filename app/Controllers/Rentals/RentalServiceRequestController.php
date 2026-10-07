<?php
declare(strict_types=1);

namespace App\Controllers\Rentals;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\InquiryStore;
use App\Services\RentalAccount;
use App\Services\RentalCart;
use App\Services\RentalServiceRequests;

final class RentalServiceRequestController extends Controller
{
    private function user(): ?array
    {
        return (new RentalAccount($this->db(), new RentalCart()))->current();
    }

    private function store(): RentalServiceRequests { return new RentalServiceRequests($this->db()); }

    private function signIn(string $destination): Response
    {
        $_SESSION['rentals_after_auth'] = $destination;
        return $this->redirect('rentals/account')->noCache();
    }

    private function unavailable(bool $admin = false, ?array $user = null): Response
    {
        return $this->render($admin ? 'rentals.admin.service-requests' : 'rentals.service-requests', [
            'rows' => [], 'statusFilter' => 'all', 'term' => '', 'section' => 'service-requests',
            'adminUser' => $user, 'unavailable' => true,
        ], 503)->noCache();
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->user();
        if (!$user) { return $this->signIn('services/' . (int) $id . '/request'); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(); }
        $service = $store->service((int) $id);
        if (!$service) { return Response::notFound()->noCache(); }
        // One current token per form/service; opening a new form starts a new request.
        $key = bin2hex(random_bytes(32));
        $_SESSION['rentals_service_form_keys'][(int) $id] = $key;
        return $this->render('rentals.service-request', [
            'service' => $service, 'user' => $user, 'formKey' => $key, 'fields' => [], 'errors' => [],
        ])->noCache();
    }

    public function submit(Request $request, string $id): Response
    {
        $user = $this->user();
        if (!$user) { return $this->signIn('services/' . (int) $id . '/request'); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(); }
        $serviceId = (int) $id;
        $key = $request->string('request_key');
        $expected = $_SESSION['rentals_service_form_keys'][$serviceId] ?? '';
        $fields = [];
        foreach (['phone', 'start_date', 'end_date', 'location', 'details'] as $field) { $fields[$field] = $request->string($field); }
        $errors = RentalServiceRequests::errors($fields);
        if (!is_string($expected) || $expected === '' || !hash_equals($expected, $key)) {
            $errors['form'] = 'This request form expired. Open the service again and retry.';
        }
        if ($errors === []) {
            try {
                $record = $store->submit((int) $user['id'], $serviceId,
                    hash('sha256', $user['id'] . '|' . $serviceId . '|' . $key), $fields);
                // Reuses the existing inquiry email implementation/configuration unchanged.
                $store->notify($record, $this->container->get(InquiryStore::class));
                $_SESSION['rentals_service_notice'] = 'Service request ' . $record['reference'] . ' received. The team will review your requirements.';
                return $this->redirect('rentals/service-requests', 303)->noCache();
            } catch (\RuntimeException $e) {
                $errors['form'] = 'The request could not be saved. Check that the service is still accepting requests and try again.';
            } catch (\Throwable $e) {
                error_log('Rentals service request save failed');
                $errors['form'] = 'Service request could not be saved right now. Please try again later.';
            }
        }
        $service = $store->service($serviceId);
        if (!$service) { return Response::notFound()->noCache(); }
        return $this->render('rentals.service-request', [
            'service' => $service, 'user' => $user, 'formKey' => $key, 'fields' => $fields, 'errors' => $errors,
        ], 422)->noCache();
    }

    public function history(Request $request): Response
    {
        $user = $this->user();
        if (!$user) { return $this->signIn('service-requests'); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(); }
        $status = $request->string('status', 'all');
        $status = isset(RentalServiceRequests::STATUSES[$status]) ? $status : 'all';
        return $this->render('rentals.service-requests', [
            'rows' => ($page=$store->historyPage((int)$user['id'],$status,$request->int('page',1)))['rows'], 'pagination'=>$page, 'statusFilter' => $status,
        ])->noCache();
    }

    private function admin(): ?array
    {
        $user = $this->user();
        return $user && in_array(strtolower((string) $user['role']), ['admin', 'superadmin'], true) ? $user : null;
    }

    public function adminList(Request $request): Response
    {
        $admin = $this->admin();
        if (!$admin) { return Response::forbidden()->noCache(); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(true, $admin); }
        $status = $request->string('status', 'pending');
        $status = isset(RentalServiceRequests::STATUSES[$status]) ? $status : 'all';
        $term = mb_substr($request->string('q'), 0, 100);
        return $this->render('rentals.admin.service-requests', [
            'rows' => ($page=$store->adminPage($status,$term,$request->int('page',1)))['rows'], 'pagination'=>$page, 'statusFilter' => $status, 'term' => $term,
            'section' => 'service-requests', 'adminUser' => $admin,
        ])->noCache();
    }

    public function adminView(Request $request, string $id): Response
    {
        $admin = $this->admin();
        if (!$admin) { return Response::forbidden()->noCache(); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(true, $admin); }
        $record = $store->find((int) $id);
        if (!$record) { return Response::notFound()->noCache(); }
        return $this->render('rentals.admin.service-request', [
            'record' => $record, 'workflowReady'=>$store->workflowReady(), 'section' => 'service-requests', 'adminUser' => $admin,
        ])->noCache();
    }

    public function review(Request $request, string $id): Response
    {
        $admin = $this->admin();
        if (!$admin) { return Response::forbidden()->noCache(); }
        $store = $this->store();
        if (!$store->ready()) { return $this->unavailable(true, $admin); }
        if (!$store->find((int) $id)) { return Response::notFound()->noCache(); }
        try {
            $changed = $store->review((int) $id, (int) $admin['id'], $request->string('decision'), $request->string('customer_message'));
            if ($changed) { $this->container->get(\App\Services\RentalNotification::class)->serviceUpdated((int)$id,$request->string('decision')); }
            $_SESSION['rentals_service_notice'] = $changed ? 'Service request reviewed.' : 'This request was already reviewed. Its decision is unchanged.';
        } catch (\RuntimeException $e) {
            $_SESSION['rentals_service_notice'] = 'Choose Approve or Reject and keep the customer message within 1,000 characters.';
        }
        return $this->redirect('rentals/admin/service-requests/' . (int) $id, 303)->noCache();
    }
    public function customerView(Request $request,string $id): Response
    {
        $user=$this->user();
        if(!$user) { return $this->signIn('service-requests/'.(int)$id); }
        $store=$this->store();
        if(!$store->ready()) { return $this->unavailable(); }
        $record=$store->find((int)$id);
        if(!$record) { return Response::notFound()->noCache(); }
        if(in_array(strtolower($user['role']),['admin','superadmin'],true)) { return $this->redirect('rentals/admin/service-requests/'.(int)$id)->noCache(); }
        if((int)$record['user_id']!==(int)$user['id']) { return Response::forbidden()->noCache(); }
        $methods=$store->workflowReady()?(new \App\Services\RentalCheckout($this->db(),new RentalCart()))->activePaymentMethods():[];
        $methods=array_values(array_filter($methods,static fn($m)=>$m['type']==='manual'));
        return $this->render('rentals.service-request-view',['record'=>$record,'methods'=>$methods,'workflowReady'=>$store->workflowReady()])->noCache();
    }

    public function payment(Request $request,string $id): Response
    {
        $user=$this->user();
        if(!$user) { return $this->signIn('service-requests/'.(int)$id); }
        $store=$this->store();
        if(!$store->ready()) { return $this->unavailable(); }
        $record=$store->find((int)$id);
        if(!$record) { return Response::notFound()->noCache(); }
        if((int)$record['user_id']!==(int)$user['id']) { return Response::forbidden()->noCache(); }
        try {
            $quoteVersion=filter_var($request->input('quote_version'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
            $changed=$store->submitPayment((int)$id,(int)$user['id'],$request->int('payment_method_id'),$request->string('payment_reference'),$request->file('payment_proof'),$quoteVersion===false?null:$quoteVersion);
            if($changed) { $this->container->get(\App\Services\RentalNotification::class)->serviceUpdated((int)$id,'payment_received'); }
            $_SESSION['rentals_service_notice']=$changed?'Payment proof submitted for review.':'Your proof is already under review or approved. No duplicate was submitted.';
        } catch(\RuntimeException $e) { $_SESSION['rentals_service_notice']=$e->getMessage(); }
        catch(\Throwable $e) { error_log('Rentals service payment unavailable'); $_SESSION['rentals_service_notice']='Payment proof could not be saved. Please try again later.'; }
        return $this->redirect('rentals/service-requests/'.(int)$id,303)->noCache();
    }

    public function proof(Request $request,string $id): Response
    {
        $user=$this->user();
        if(!$user) { return $this->signIn('service-requests/'.(int)$id); }
        $store=$this->store();
        if(!$store->ready()) { return Response::notFound()->noCache(); }
        $record=$store->find((int)$id);
        if(!$record) { return Response::notFound()->noCache(); }
        if((int)$record['user_id']!==(int)$user['id'] && !in_array(strtolower($user['role']),['admin','superadmin'],true)) { return Response::forbidden()->noCache(); }
        return \App\Services\RentalPaymentProof::response($record['payment_proof_path']??null);
    }

    public function manage(Request $request,string $id): Response
    {
        $admin=$this->admin();
        if(!$admin) { return Response::forbidden()->noCache(); }
        $store=$this->store();
        if(!$store->ready()) { return $this->unavailable(true,$admin); }
        if(!$store->find((int)$id)) { return Response::notFound()->noCache(); }
        try {
            if(!$store->workflowReady()) { throw new \RuntimeException('Apply the Services deployment update first.'); }
            $action=$request->string('action');
            $changed=match($action) {
                'quote'=>$store->quote((int)$id,(int)$admin['id'],$request->string('quote_amount'),$request->string('quote_notes')),
                'payment'=>$store->reviewPayment((int)$id,(int)$admin['id'],$request->string('decision'),$request->string('payment_message')),
                'close'=>$store->close((int)$id,(int)$admin['id'],$request->string('decision'),$request->string('customer_message')),
                default=>throw new \RuntimeException('Choose a valid service action.'),
            };
            if($changed) {
                $event=match($action){'quote'=>'quoted','payment'=>'payment_'.$request->string('decision'),default=>$request->string('decision')};
                $this->container->get(\App\Services\RentalNotification::class)->serviceUpdated((int)$id,$event);
            }
            $_SESSION['rentals_service_notice']=$changed?'Service request updated.':'No change was needed. The previous decision is preserved.';
        } catch(\RuntimeException $e) { $_SESSION['rentals_service_notice']=$e->getMessage(); }
        catch(\Throwable $e) { error_log('Rentals service management unavailable'); $_SESSION['rentals_service_notice']='The service could not be updated. Please try again later.'; }
        return $this->redirect('rentals/admin/service-requests/'.(int)$id,303)->noCache();
    }

}
