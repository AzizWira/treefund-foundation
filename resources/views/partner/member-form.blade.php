@php $restoreInput = (string) old('member_id', '') === (string) ($member?->id ?? ''); @endphp
<form method="post" action="{{ $member ? url('/partner/members/'.$member->id) : route('partner.members') }}" class="form-stack">
    @csrf
    @if($member)
        @method('PUT')
        <input type="hidden" name="member_id" value="{{ $member->id }}">
    @endif
    <label>{{ $member ? 'Nama' : 'Nama lengkap' }}<input name="name" maxlength="120" value="{{ $restoreInput ? old('name', $member?->name) : $member?->name }}" required></label>
    <label>{{ $member ? 'Jabatan' : 'Jabatan / tanggung jawab' }}<input name="position" maxlength="100" value="{{ $restoreInput ? old('position', $member?->position) : $member?->position }}" required></label>
    <label>{{ $member ? 'Telepon' : 'Nomor telepon' }}<input name="phone" type="tel" maxlength="25" value="{{ $restoreInput ? old('phone', $member?->phone) : $member?->phone }}"></label>
    <button class="button {{ $member ? 'small' : '' }}">{{ $member ? 'Simpan Perubahan' : 'Simpan Anggota' }}</button>
</form>
