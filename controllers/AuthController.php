<?php

namespace Controllers;

use Core\Auth\Auth;
use Core\Http\Request;
use Core\Routing\Controller;
use Core\Support\Mail;
use Core\Valid\Hash;
use Core\Valid\Validator;
use Models\Log;
use Models\User;

class AuthController extends Controller
{
    public function login()
    {
        return $this->view('auth/login');
    }

    public function register()
    {
        return $this->view('auth/register');
    }

    public function forget()
    {
        return $this->view('auth/forget');
    }

    public function logout()
    {
        Auth::logout();
        return $this->redirect(route('login'))->with('berhasil', 'Berhasil logout');
    }

    public function auth(Request $request)
    {
        $valid = Validator::make(
            [
                'email' => $request->email,
                'password' => $request->password,
                'user' => $request->server('HTTP_USER_AGENT'),
                'ip' => $request->ip()
            ],
            [
                'email' => ['required', 'str', 'trim', 'min:5', 'max:50', 'email'],
                'password' => ['required', 'str', 'trim', 'min:8', 'max:20'],
                'user' => ['str', 'trim', 'max:150'],
                'ip' => ['str', 'trim', 'max:15']
            ]
        );

        if ($valid->fails()) {
            $request->throw($valid);
        }

        if (Auth::attempt($valid->only(['email', 'password']))) {
            Log::create([
                'user_id' => Auth::id(),
                'user_agent' => $valid->user,
                'ip_address' => $valid->ip
            ]);

            return $this->redirect(route('dashboard'));
        }

        return $this->back();
    }

    public function submit(Request $request)
    {
        $credential = $request->validate([
            'nama' => ['required', 'str', 'trim', 'min:2', 'max:25'],
            'email' => ['required', 'str', 'trim', 'min:5', 'max:50', 'email', 'dns', 'unik'],
            'password' => ['required', 'str', 'trim', 'min:8', 'max:20', 'hash']
        ]);

        $credential['role_id'] = 2;
        User::create($credential);

        return $this->redirect(route('login'))->with('berhasil', 'Berhasil registrasi, silahkan login');
    }

    public function send(Request $request, Mail $mail)
    {
        $request->validate([
            'email' => ['required', 'str', 'trim', 'min:5', 'max:50', 'dns', 'email']
        ]);

        $user = User::find($request->email, 'email')->fail(fn () => false);

        if ($user === false) {
            return $this->back()->with('gagal', 'Email tidak ada');
        }

        $key = Hash::rand(30);

        $mail->addTo($request->email)
            ->subjek('Reset Password Dikit Link')
            ->pesan($this->view('email/reset', [
                'nama' => $user->nama,
                'link' => route('reset', $key)
            ]));

        session()->unset('key');
        session()->unset('email');

        if ($mail->send()) {
            session()->set('key', $key);
            session()->set('email', $request->email);

            return $this->back()->with('berhasil', 'Cek email, termasuk di folder spam');
        }

        return $this->back()->with('gagal', 'Gagal mengirim email');
    }

    public function reset(Request $request, $id)
    {
        $success = false;

        if (hash_equals(session()->get('key', Hash::rand(10)), $id)) {
            $valid = Validator::make(
                [
                    'user' => $request->server('HTTP_USER_AGENT'),
                    'ip' => $request->ip()
                ],
                [
                    'user' => ['str', 'trim', 'max:150'],
                    'ip' => ['str', 'trim', 'max:15']
                ]
            );

            Auth::login(User::find(session()->get('email'), 'email'));
            Log::create([
                'user_id' => Auth::id(),
                'user_agent' => $valid->user,
                'ip_address' => $valid->ip
            ]);

            $success = true;
        }

        session()->unset('key');
        session()->unset('email');

        if ($success) {
            return $this->redirect(route('profile'))->with('berhasil', 'Silahkan ganti password anda !');
        }

        return $this->redirect(route('forget'))->with('gagal', 'Kode tidak valid !');
    }
}
