<?php

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Request;
use Core\Validator;
use Models\Link;
use Models\Stat;

class StatistikController extends Controller
{
    public function index(Link $link)
    {
        $id = Auth::user()->id;
        $getstats = $link->getStats($id);
        $sumstats = $link->sumStats($id);

        return $this->view('statistik', [
            'last_month' => $link->lastMonth($id),
            'user_agent' => $getstats('user_agent'),
            'ip_address' => $getstats('ip_address'),
            'jumlah_link' => $sumstats->jumlah_link ?? 0,
            'total_pengunjung' => $sumstats->total_pengunjung ?? 0
        ]);
    }

    public function click(Request $request, $id)
    {
        $valid = Validator::make([
            'id' => $id
        ], [
            'id' => ['trim', 'slug', 'max:30']
        ]);

        if ($valid->fails()) {
            return $this->view('hilang');
        }

        $link = Link::find($valid->id, 'name');

        if (empty($link->id)) {
            return $this->view('hilang');
        }

        Stat::create([
            'link_id' => $link->id,
            'user_agent' => $request->server('HTTP_USER_AGENT'),
            'ip_address' => $request->ip()
        ]);

        header_remove();
        http_response_code(301);
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . trim($link->link), true, 301);
    }
}
