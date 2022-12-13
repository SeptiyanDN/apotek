<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Setting;
use PDF;

class PenjualanController extends Controller
{

    public function index()
    {
        $penjualan = Penjualan::orderBy('id_penjualan','desc')->get();
        foreach($penjualan as $item) {
            if($item->total_item == 0){
                $item->delete();
            }
        }
        return view('penjualan.index');
    }

    public function data()
    {
        $penjualan = penjualan::orderBy('id_penjualan', 'desc')->get();
            return datatables()
            ->of($penjualan)
            ->addIndexColumn()
            ->addColumn('total_item', function ($penjualan) {
                return $penjualan->total_item;
            })
            ->addColumn('total_harga', function ($penjualan) {
                return 'Rp '. format_uang($penjualan->total_harga);
            })
            ->addColumn('bayar', function ($penjualan) {
                return 'Rp '. format_uang($penjualan->bayar);
            })
            ->addColumn('tanggal', function ($penjualan) {
                return tanggal_indonesia($penjualan->created_at, false);
            })
            ->addColumn('member', function ($penjualan) {
                return $penjualan->member->nama ?? '-';
            })
            ->editColumn('diskon', function ($penjualan) {
                return $penjualan->diskon . '%';
            })
            ->editColumn('kasir', function ($penjualan) {
                return $penjualan->user->name ?? '-';
            })
            ->editColumn('status', function ($penjualan) {
                return $penjualan->status ?? '-';
            })
            ->addColumn('Action', function ($penjualan){
                if($penjualan->status == 'Belum Bayar' && $penjualan->metode_pembayaran=='Transfer'){
                    return '
                    <button type="button" onclick="showDetail(`'. route('penjualan.show', $penjualan->id_penjualan) .'`)" class= "btn btn-xs btn-info"><i class= "fa fa-eye"> Lihat</i></button>
                    <button type="button" onclick="deleteData(`'. route('penjualan.destroy', $penjualan->id_penjualan) .'`)" class= "btn btn-xs btn-danger"><i class= "fa fa-trash"></i> Hapus</button>
                    <a type="button" href="/pay/'.$penjualan->id_penjualan .'" target="_blank" class= "btn btn-xs btn-warning"><i class= "fa fa-eye"> Bayar</i></a>
                    ';
                }else{
                    return '
                    <button type="button" onclick="showDetail(`'. route('penjualan.show', $penjualan->id_penjualan) .'`)" class= "btn btn-xs btn-info"><i class= "fa fa-eye"></i></button>
                    <a type="button"  href="'.route('transaksi.selesai',$penjualan->id_penjualan).'" target="_blank" class= "btn btn-xs btn-warning"><i class= "fa fa-print"></i></a>

                    <button type="button" onclick="deleteData(`'. route('penjualan.destroy', $penjualan->id_penjualan) .'`)" class= "btn btn-xs btn-danger"><i class= "fa fa-trash"></i></button>
                    ';
                }

            })
            ->rawColumns(['Action'])
            ->make(true);

    }

    public function create()
    {
        $penjualan = new Penjualan();
        $penjualan->id_member = null;
        $penjualan->total_item = 0;
        $penjualan->total_harga = 0;
        $penjualan->diskon = 0;
        $penjualan->bayar = 0;
        $penjualan->diterima = 0;
        $penjualan->id_user = auth()->id();
        $penjualan->save();

        session(['id_penjualan' => $penjualan->id_penjualan]);
        return redirect()->route('transaksi.index');

    }

    public function store(Request $request)
    {
        $penjualan = Penjualan::findOrFail($request->id_penjualan);
        $penjualan->id_member = $request->id_member;
        $penjualan->total_item = $request->total_item;
        $penjualan->total_harga = $request->total;
        $penjualan->diskon = $request->diskon;
        $penjualan->bayar = $request->bayar;
        $penjualan->diterima = $request->diterima;
        $penjualan->metode_pembayaran = $request->metode_pembayaran;
        if($penjualan->metode_pembayaran == 'Tunai'){
            $penjualan->status = 'Sudah Bayar';
            $penjualan->update();

            $detail = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
            foreach ($detail as $item) {
                $produk = Produk::find($item->id_produk);
                $produk->stok -= $item->jumlah;
                $produk->update();
            }
            return redirect()->route('transaksi.selesai',$penjualan->id_penjualan);
        }else{
            $penjualan->status = 'Belum Bayar';
            $penjualan->update();

            // $detail = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
            // foreach ($detail as $item) {
            //     $produk = Produk::find($item->id_produk);
            //     $produk->stok -= $item->jumlah;
            //     $produk->update();
            // }
            return redirect('/pay/'.$request->id_penjualan);
        }

    }

    public function show($id)
    {
        $detail = PenjualanDetail::with('produk')->where('id_penjualan', $id)->get();
        return datatables()
        ->of($detail)
        ->addIndexColumn()
        ->addColumn('kode_produk', function ($detail) {
            return '<span class="label label-primary">'. $detail->produk->kode_produk .'</span>';
        })
        ->addColumn('nama_produk', function ($detail) {
            return $detail->produk->nama_produk;
        })
        ->addColumn('harga_jual', function ($detail) {
            return 'Rp '. format_uang($detail->harga_jual);
        })
        ->addColumn('jumlah', function ($detail) {
            return $detail->jumlah;
        })
        ->addColumn('subtotal', function ($detail) {
            return 'Rp '. format_uang($detail->subtotal);
        })
        ->rawColumns(['kode_produk'])
        ->make(true);
    }

    public function destroy($id)
    {
        $penjualan = Penjualan::find($id);
        $detail    = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)->get();
        foreach ($detail as $item) {
            $produk = Produk::find($item->id_produk);
            if ($produk) {
                $produk->stok += $item->jumlah;
                $produk->update();
            }

            $item->delete();
        }

        $penjualan->delete();

        return response(null, 204);
    }

    public function selesai($id_penjualan)
    {
        $penjualan = Penjualan::where('id_penjualan',$id_penjualan)->first();
        $detail = PenjualanDetail::with('produk')
            ->where('id_penjualan', $id_penjualan)
            ->get();
        $setting = Setting::first();
        $id_penjualan = $id_penjualan;
        return view('penjualan.selesai', compact('setting','detail','id_penjualan'));
    }

    // public function notaKecil()
    // {
    //     $setting = Setting::first();
    //     $penjualan = Penjualan::find(session('id_penjualan'));
    //     if (! $penjualan) {
    //         abort(404);
    //     }
    //     $detail = PenjualanDetail::with('produk')
    //     ->where('id_penjualan', session('id_penjualan'))
    //     ->get();

    //     return view('penjualan.nota-kecil', compact('setting', 'penjualan', 'detail'));
    // }
    public function notaKecil($id_penjualan)
    {
              $setting = Setting::first();
        $penjualan = Penjualan::where('id_penjualan',$id_penjualan)->first();
        if (! $penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
        ->where('id_penjualan', session('id_penjualan'))
        ->get();

        return view('penjualan.nota-kecil', compact('setting', 'penjualan', 'detail'));
    }
    public function notaBesar()
    {
        $setting = Setting::first();
        $penjualan = Penjualan::find(session('id_penjualan'));
        if (! $penjualan) {
            abort(404);
        }
        $detail = PenjualanDetail::with('produk')
        ->where('id_penjualan', session('id_penjualan'))
        ->get();

        $pdf = PDF::loadView('penjualan.nota-besar', compact('setting', 'penjualan', 'detail'));
        $pdf->setPaper(0,0,609,440, 'potrait');
        return $pdf->stream('Nota - ' . date('Y-m-d-his') . '.pdf');
    }


}
