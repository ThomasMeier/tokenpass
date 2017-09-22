@extends('accounts.base')

@section('htmltitle', 'Account Settings')

@section('body_class', 'dashboard')

@section('accounts_content')

<section class="title">
    <span class="heading">Account Settings</span>
</section>

<section>
    <form method="POST" action="/auth/update">

        {!! csrf_field() !!}

        <label for="Name">Name</label>
        <input name="name" type="text" id="Name" placeholder="Satoshi Nakamoto" value="{{ old('name') }}">

        <label for="Name">Username</label>
        <input value="{{ $model['username'] }}" readonly>

        <label for="Email">Email address</label>
        <input required="required" name="email" type="email" id="Email" placeholder="youremail@yourwebsite.com" value="{{ old('email') }}">

        <div class="input-group">
            <label for="Password">New Password</label>
            <input type="password" id="Password" name="new_password">
            <div class="sublabel">Enter a new password only if you wish to update your password</div>
        </div>

        <label for="Password">Confirm New Password</label>
        <input type="password" id="Password" name="new_password_confirmation">

        <div class="input-group">
            <label>Enable Second Factor on account?</label>
            <input id="account-second-factor" name="second_factor" type="checkbox" class="toggle toggle-round-flat" @if($model->second_factor == 1) checked="checked" @endif value="1" >
            <label for="account-second-factor"></label>
        </div>

        <div class="input-group" id="app">
            <label for="avatar_upload">Profile Image</label>
            <!-- only show the menu when ready -->
            <ul v-show="uploadedFiles.length > 0">
                <!-- loop through the completed files -->
                <li v-for="file in uploadedFiles">Name: <em>@{{ file.name }}</em> Size: <em>@{{ file.size | prettyBytes }}</em></li>
            </ul>
            <!-- only show when ready, fileProgress is a percent -->
            <div class="progress-bar" :style="{ width: fileProgress + '%'}" v-show="fileProgress > 0" ></div>
            <!-- message for all uploads completing -->
            <p v-if="allFilesUploaded"><strong>File Uploaded</strong></p>
            <!-- full usage example -->
            <file-upload class="my-file-uploader" name="myFile" id="avatarUpload" action="/image/store"></file-upload>
        </div>

        <hr>
        <div class="input-group">
            <label for="Password">Current Password</label>
            <input required="required" type="password" id="Password" name="password">
            <div class="sublabel">Please verify your current password to save your changes</div>
        </div>
        <button type="submit">Save</button>
    </form>

    <hr>

    <!-- Connect to Civic -->
    <div>
        @if ($user->civic_enabled)
            <form id="disconnect-civic-form" action="/auth/disconnect_civic" method="post">
                {!! csrf_field() !!}
                <a id="disconnect-civic" class="signature__cts" style="width: 400px">
                    <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
                    Disconnect from Civic
                </a>
            </form>
        @else
            <a id="civic-auth" class="signature__cts" style="width: 400px">
                <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
                Connect to Civic
            </a>
        @endif
    </div>

</section>

@endsection

@section('page-js')
    <script>
        var app = new Vue({
            el: '#app',
            data: {
                uploadedFiles: [], // my list for the v-for
                fileProgress: 0, // global progress
                allFilesUploaded: false
            },
            events: {
                onFileClick: function(file) {
                    //console.log('onFileClick', file);
                },
                onFileChange: function(file) {
                    //console.log('onFileChange', file);
                    this.fileProgress = 0;
                    this.allFilesUploaded = false;
                },
                beforeFileUpload: function(file) {
                    // called when the upload handler is called
                    console.log('beforeFileUpload', file);
                },
                afterFileUpload: function(file) {
                    console.log('afterFileUpload', file);
                },
                onFileProgress: function(progress) {
                    console.log('onFileProgress', progress);
                    // update our progress bar
                    this.fileProgress = progress.percent;
                },
                onFileUpload: function(file, res) {
                    console.log('onFileUpload', file, res);
                    // update our list
                    this.uploadedFiles.push(file);
                },
                onFileError: function(file, res) {
                    console.error('onFileError', file, res);
                },
                onAllFilesUploaded: function(files) {
                    console.log('onAllFilesUploaded', files);
                    // everything is done!
                    this.allFilesUploaded = true;
                }
            }
        });
    </script>
@endsection
