<table style="width: 100%; height: 100%">
    <tr>
        <td valign="middle" align="center">
            <h3 style="font-family: sans-serif; color: navy">System Trigger Created.</h3>
        </td>
    </tr>
</table>
<script type="text/javascript">
    var tt = function () {
        if (Desktop.window.list['{$system->getWindowId()}']) {
            Desktop.window.list['{$system->getWindowId()}']._close();
        }
    }
    window.setTimeout(tt,3000);
</script>
