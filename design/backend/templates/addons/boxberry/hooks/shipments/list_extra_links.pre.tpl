{if $shipment.boxberry_label}
    <li>
        {btn type="list" text={__("boxberry.print_label")} href="`$shipment.boxberry_label`" target="_blank"}
    </li>
{/if}
