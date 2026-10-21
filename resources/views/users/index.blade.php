@extends('layouts.app')

@section('title', 'User Management')
@section('subtitle', 'Accounts and access levels')

@section('breadcrumb')
    <x-breadcrumb :items="[['label' => 'User Management']]" />
@endsection

@section('content')
    <div class="grid grid-3">
        <div class="card" style="grid-column: span 2">
            <div class="card-head">
                <h3>Accounts</h3>
                <div class="spacer"></div>
                <span class="sub">{{ $users->count() }} users</span>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $user->name }}</div>
                                    <div class="fs-11 text-muted mono">{{ $user->username ?: $user->email }}</div>
                                </td>
                                <td><span class="badge {{ $user->isAdmin() ? 'high' : 'neutral' }}">{{ $user->roleLabel() }}</span></td>
                                <td>
                                    <span class="badge {{ $user->is_active ? 'success' : 'critical' }}">
                                        {{ $user->is_active ? 'active' : 'disabled' }}
                                    </span>
                                </td>
                                <td class="fs-11 text-secondary">
                                    @if ($user->last_login_at)
                                        {{ $user->last_login_at->diffForHumans() }}
                                        <div class="text-muted mono">{{ $user->last_login_ip }}</div>
                                    @else
                                        never
                                    @endif
                                </td>
                                <td class="nowrap">
                                    <button type="button" class="btn ghost sm" data-edit='@json($user)'>Edit</button>
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline"
                                              onsubmit="return confirm('Delete {{ $user->handle() }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn danger sm" type="submit">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h3 id="formTitle">New User</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('users.store') }}" id="userForm">
                    @csrf
                    <div id="methodField"></div>

                    <div class="form-row">
                        <label for="name">Full name</label>
                        <input id="name" name="name" class="input" required>
                    </div>

                    <div class="form-row">
                        <label for="username">Username</label>
                        <input id="username" name="username" class="input" required
                               autocomplete="off">
                    </div>

                    <div class="form-row">
                        <label for="email">Email (optional)</label>
                        <input id="email" name="email" type="email" class="input">
                    </div>

                    <div class="form-row">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="select" required>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="theme">Preferred theme</label>
                        <select id="theme" name="theme" class="select">
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" class="input" autocomplete="new-password">
                        <span class="fs-11 text-muted" id="passwordHint">Minimum 10 characters with letters, numbers and symbols.</span>
                    </div>

                    <div class="form-row">
                        <label for="password_confirmation">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                    </div>

                    <div class="form-row" id="activeRow" style="display:none">
                        <label class="check-row">
                            <input type="checkbox" name="is_active" value="1" id="is_active" checked>
                            Account is active
                        </label>
                    </div>

                    <div class="row" style="gap:8px;margin-top:6px">
                        <button type="submit" class="btn primary" id="submitBtn">Create user</button>
                        <button type="button" class="btn ghost" id="cancelEdit" style="display:none">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('userForm');
    var title = document.getElementById('formTitle');
    var methodField = document.getElementById('methodField');
    var activeRow = document.getElementById('activeRow');
    var cancel = document.getElementById('cancelEdit');
    var submitBtn = document.getElementById('submitBtn');
    var password = document.getElementById('password');
    var hint = document.getElementById('passwordHint');

    function reset() {
        form.action = @json(route('users.store'));
        methodField.innerHTML = '';
        title.textContent = 'New User';
        form.reset();
        activeRow.style.display = 'none';
        cancel.style.display = 'none';
        submitBtn.textContent = 'Create user';
        password.required = true;
        hint.textContent = 'Minimum 10 characters with letters, numbers and symbols.';
    }

    document.querySelectorAll('[data-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var user = JSON.parse(btn.getAttribute('data-edit'));

            form.action = '/users/' + user.id;
            methodField.innerHTML = '<input type="hidden" name="_method" value="PUT">';
            title.textContent = 'Edit: ' + user.name;
            form.querySelector('[name=name]').value = user.name;
            form.querySelector('[name=username]').value = user.username || '';
            form.querySelector('[name=email]').value = user.email || '';
            form.querySelector('[name=role]').value = user.role;
            form.querySelector('[name=theme]').value = user.theme || 'dark';
            document.getElementById('is_active').checked = !!user.is_active;
            activeRow.style.display = '';
            cancel.style.display = 'inline-flex';
            submitBtn.textContent = 'Save changes';
            password.required = false;
            password.value = '';
            document.getElementById('password_confirmation').value = '';
            hint.textContent = 'Leave blank to keep the current password.';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    cancel.addEventListener('click', reset);
})();
</script>
@endpush
