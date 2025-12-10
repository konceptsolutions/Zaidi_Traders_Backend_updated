<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\SendPo;
use App\Models\Person;
use App\Models\PurchaseOrder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendEmailController extends Controller
{
    public function index(Request $request)
    {

        $suplierId = PurchaseOrder::where('id', $request->id)->first();
        if ($suplierId->is_approved == 0)
            return ['status' => 'error', 'message' => "Purchase order is not approved yet"];
        $suplierEmail = Person::select('email')->where('id', $suplierId->person_id)->first();
        if (!$suplierEmail || $suplierEmail->email == null)
            return ['status' => 'error', 'message' => "Supplier Email not Found"];
        else {
            $data['email'] = $suplierEmail->email;
            $data['subject'] = "Purchase Oder";
            $data['link'] = $request->link;
            Mail::send('sendPo', $data, function ($message) use ($data) {
                $message->to($data["email"])
                    ->subject($data["subject"]);
                $base64 = substr($data['link'], strpos($data['link'], "base64,") + 7);
                $message->embedData(base64_decode($base64), 'PO.pdf');
            });
            $po = PurchaseOrder::find($request->id);
            $po->is_mailsent = 1;
            $po->save();
            // Mail::to($suplierEmail)->send(new SendPo($data));
            return ['status' => 'ok', 'message' => "Email Send"];
        }
    }
}
