@extends('layouts.app')
@section('title','Data Anggota Poktan')
@section('content')
<div class="page-heading"><div><span class="eyebrow">KELOMPOK TANI</span><h1>Data Anggota Poktan</h1><p>{{ $partner?->group_name ?? 'Lengkapi profil kemitraan untuk mengelola anggota.' }}</p></div></div>
<div class="form-layout">
    <section class="panel">
        <h2>Anggota Kelompok</h2>
        <div class="table-scroll"><table>
            <thead><tr><th>NAMA</th><th>PERAN</th><th>KONTAK</th><th>AKSI</th></tr></thead>
            <tbody>
                @forelse($members as $member)
                    <tr><td>{{ $member->name }}</td><td>{{ $member->position }}</td><td>{{ $member->phone ?: '—' }}</td>
                        <td class="table-action-cell"><details @if((string) old('member_id') === (string) $member->id) open @endif>
                            <summary class="text-link">Edit</summary>
                            @include('partner.member-form', ['member' => $member])
                        </details></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty/></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
    @if($partner)
        <section class="panel"><h2>Tambah Anggota</h2>@include('partner.member-form', ['member' => null])</section>
    @endif
</div>
@endsection
