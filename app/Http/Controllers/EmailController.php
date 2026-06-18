<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function showForm()
    {
        return view('send-email');
    }

    public function sendEmail(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $email = 'kulacak001@gmail.com';
        $subject = $request->input('subject');
        $message = $request->input('message');

        Mail::raw($message, function ($mail) use ($email, $subject) {
            $mail->to($email)
                 ->subject($subject);
        });

        return back()->with('success', 'Email sent successfully!');
    }
}