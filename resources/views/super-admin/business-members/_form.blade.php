<form method="POST" action="{{ $action }}">
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <style>
        .member-form {
            display: grid;
            gap: 18px;
        }

        .form-layout {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 16px;
            align-items: start;
        }

        .form-section {
            display: grid;
            gap: 14px;
        }

        .form-section h3 {
            margin: 0;
            font-size: 18px;
        }

        .form-section p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .field-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            grid-column: span 6;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #dbe4f0;
        }

        .checkbox-row input {
            width: 18px;
            height: 18px;
        }

        .preview-card {
            display: grid;
            gap: 14px;
        }

        .preview-item {
            border-radius: 16px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            padding: 14px;
        }

        .preview-item .label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .preview-item .value {
            margin-top: 8px;
            font-weight: 650;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        @media (max-width: 900px) {
            .form-layout,
            .field {
                grid-column: 1 / -1;
            }

            .form-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="member-form">
        <div class="form-layout">
            <section class="card form-section">
                <div>
                    <h3>{{ $heading }}</h3>
                    <p>{{ $description }}</p>
                </div>

                @if (!($isEdit ?? false))
                    <div class="field-grid">
                        <div class="field">
                            <label for="full_name">Full name</label>
                            <input id="full_name" name="full_name" value="{{ old('full_name', $form['full_name']) }}" autocomplete="name">
                            @error('full_name') <div class="error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $form['email']) }}" autocomplete="email">
                            @error('email') <div class="error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="temporary_password">Temporary password</label>
                            <input id="temporary_password" name="temporary_password" type="password" value="{{ old('temporary_password', $form['temporary_password']) }}" autocomplete="new-password">
                            @error('temporary_password') <div class="error">{{ $message }}</div> @enderror
                            <div class="help">Used only when the email does not already exist.</div>
                        </div>

                        <div class="field">
                            <label for="send_invitation_later">Invitation</label>
                            <div class="checkbox-row">
                                <input id="send_invitation_later" name="send_invitation_later" type="checkbox" value="1" @checked(old('send_invitation_later', $form['send_invitation_later']))>
                                <span>Send invitation later</span>
                            </div>
                            @error('send_invitation_later') <div class="error">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @else
                    <div class="preview-card">
                        <div class="preview-item">
                            <div class="label">Member</div>
                            <div class="value">{{ $member->name }}</div>
                        </div>
                        <div class="preview-item">
                            <div class="label">Email</div>
                            <div class="value">{{ $member->email }}</div>
                        </div>
                        <div class="preview-item">
                            <div class="label">Email lock</div>
                            <div class="value">Cannot be edited from the membership module.</div>
                        </div>
                    </div>
                @endif
            </section>

            <aside class="card form-section">
                <div>
                    <h3>Membership settings</h3>
                    <p>Role, status, and metadata stay on the pivot record.</p>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="role">Role</label>
                        <select id="role" name="role">
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', $form['role']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $form['status']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field full">
                        <label for="metadata">Metadata JSON</label>
                        <textarea id="metadata" name="metadata" placeholder='{"source":"super-admin"}'>{{ old('metadata', $form['metadata']) }}</textarea>
                        @error('metadata') <div class="error">{{ $message }}</div> @enderror
                        <div class="help">Optional JSON stored on the `business_user` pivot.</div>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="button primary" type="submit">{{ $submitLabel }}</button>
                    <a class="button ghost" href="{{ route('super-admin.businesses.members.index', $business) }}">Cancel</a>
                </div>
            </aside>
        </div>
    </div>
</form>
