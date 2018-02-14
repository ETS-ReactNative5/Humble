{assign var=data value=$element->load()}
<style type="text/css">
    .paradigm-config-descriptor {
        font-size: .8em; font-family: serif; letter-spacing: 2px;
    }
    .paradigm-config-field {
        font-size: 1em; font-family: sans-serif; text-align: right; padding-right: 4px;
    }
    .paradigm-config-cell {
        width: 33%; margin: 1px; background-color: #e8e8e8;  border: 1px solid #d0d0d0; padding-left: 2px
    }
</style>
<table style="width: 100%; height: 100%; border-spacing: 1px;">
    <tr style="height: 30px">
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Type</div><div class="paradigm-config-field">{$data.type}</div></td>
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Shape</div><div class="paradigm-config-field">{$data.shape}</div></td>
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Mongo ID</div><div class="paradigm-config-field">{$data.id}</div></td>
    </tr>
    <tr style="height: 30px">
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Namespace</div><div class="paradigm-config-field">{$data.namespace}</div></td>
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Method</div><div class="paradigm-config-field">{$data.method}</div></td>
        <td class="paradigm-config-cell"><div class="paradigm-config-descriptor">Component</div><div class="paradigm-config-field">{$data.component}</div></td>
    </tr>
    <tr>
        <td colspan="3" align="center" valign="middle">
            <form name="config-text-message-form" id="config-text-message-form-{$data.id}" onsubmit="return false">
                <input type="hidden" name="id" id="id_{$data.id}" value="{$data.id}" />
                <input type="hidden" name="windowId" id="windowId_{$data.id}" value="{$helper->getWindowId()}" />
                <table>
                    <tr>
                        <td>Number:  <input type="text" name="number" id="config-text-number-{$data.id}" value="{if (isset($data.number))}{$data.number}{/if}" /></td>
                    </tr>
                    <tr>
                        <td>Message: <input type="text" name="message" id="config-text-message-{$data.id}" value="{if (isset($data.message))}{$data.message}{/if}" /></td>
                    </tr>
                    <tr>
                        <td><input type="submit" value=" Save " /></td>
                    </tr>
                </table>

            </form>
        </td>
    </tr>
    <tr>
        <td colspan="3" height="20" valign="top" style="font-family: sans-serif; font-size: .8em">&copy; 2014-Present, Humble Project, all rights reserved</td>
    </tr>
</table>
<script type="text/javascript">
    Form.intercept($('#config-text-message-form-{$data.id}').get(),'{$data.id}','/paradigm/element/update',"{$helper->getWindowId()}");
</script>