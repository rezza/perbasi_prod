@extends('compro.layout')

@section('title', 'Masuk - PERBASI Kabupaten Pemalang')

@section('content')

<section class="bg-navy-900 pt-16">
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Masuk</h1>
        <p class="mt-3 text-gray-300">Masuk dengan akun member untuk melanjutkan pendaftaran event</p>
    </div>
</section>

<section class="max-w-md mx-auto px-4 -mt-8 pb-16">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <form method="POST" action="{{ route('compro.login') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email / No. HP</label>
                <input type="text" name="username" value="{{ old('username') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>

            <button type="submit"
                    class="w-full bg-brandred-600 hover:bg-brandred-700 text-white font-semibold py-2.5 rounded-lg shadow transition">
                Masuk
            </button>
        </form>

        <p class="text-center text-sm text-gray-600 mt-6">
            Belum punya akun?
            <a href="{{ route('compro.register') }}" class="text-brandred-600 font-semibold hover:underline">Daftar akun</a>
        </p>
    </div>
</section>

@endsection
