<form method="POST" enctype="multipart/form-data" action="/tadreeblms/public/test-scorm-upload">
    @csrf

    <input type="file" name="zip_file">

    <button type="submit">
        Upload SCORM
    </button>
</form>