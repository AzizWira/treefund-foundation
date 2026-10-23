@extends('layouts.app')
@section('title','Data Anggota Poktan')
@section('content')
<div class="page-heading"><div><span class="eyebrow">KELOMPOK TANI</span><h1>Data Anggota Poktan</h1><p>{{ $partner?->group_name ?? 'Lengkapi profil kemitraan untuk mengelola anggota.' }}</p></div></div>
<div class="form-layout">
    <section class="panel">
        <h2>Anggota Kelompok</h2>
        <x-filters/>
        <div class="table-scroll"><table>
            <thead><tr><th>NAMA</th><th>PERAN</th><th>KONTAK</th><th>AKSI</th></tr></thead>
            <tbody>
                @forelse($members as $member)
                    <tr><td>{{ $member->name }}</td><td>{{ $member->position }}</td><td>{{ $member->phone ?: '—' }}</td>
                        <td class="table-action-cell"><details @if((string) old('member_id') === (string) $member->id) open @endif>
                            <summary class="text-link">Edit</summary>
                            @include('partner.member-form', ['member' => $member])
                        </details>
                            <form method="post" action="/partner/members/{{ $member->id }}" data-confirm="Hapus anggota ini dari kelompok?" data-confirm-name="{{ $member->name }}">
                                @csrf @method('DELETE')<button class="text-link danger bare-button">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty/></td></tr>
                @endforelse
            </tbody>
        </table></div>
        {{ $members->links() }}
    </section>
    @if($partner)
        <section class="panel"><h2>Tambah Anggota</h2>@include('partner.member-form', ['member' => null])</section>
    @endif
</div>
<dialog class="review-dialog confirmation-dialog" id="member-delete-confirm" aria-labelledby="member-delete-title"><div class="review-dialog-header"><strong id="member-delete-title">Hapus Anggota Kelompok</strong><form method="dialog"><button class="icon-button" aria-label="Tutup konfirmasi"><span class="material-symbols-outlined">close</span></button></form></div><div class="panel review-detail-panel"><div class="modal-icon-badge"><span class="material-symbols-outlined">person_remove</span></div><h2>Hapus anggota kelompok ini?</h2><p>Anggota <strong data-member-name></strong> akan dihapus dari daftar kelompok Anda.</p><div class="modal-actions"><button type="button" class="button logout-danger-btn" data-confirm-delete>Ya, Hapus Anggota</button><button type="button" class="button secondary" data-dialog-close>Batal</button></div></div></dialog>
@endsection
