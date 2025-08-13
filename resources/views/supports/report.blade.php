
<div class="container">

    <div class="table-responsive">
        <table class="table table-striped table-bordered table-sm" border="">
            <thead class="table-dark">
                <tr>
                    @foreach(array_keys($supports[0] ?? []) as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($supports as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="100%" class="text-center">No hay datos disponibles</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

