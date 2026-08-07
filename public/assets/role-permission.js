// Update permission item visual state based on checkbox
function updatePermissionItemState(checkbox) {
    const $item = $(checkbox).closest(".permission-item");
    if ($(checkbox).is(":checked")) {
        $item.addClass("checked");
    } else {
        $item.removeClass("checked");
    }
}

// Initialize permission item states on page load
$(".permission-checkbox").each(function () {
    updatePermissionItemState(this);
});

// Handle permission checkbox changes
$(".permission-checkbox").on("change", function () {
    updatePermissionItemState(this);
    updateParentCheckboxes();
});

// Check All functionality
$("#checkAll").on("change", function () {
    const isChecked = $(this).is(":checked");
    $(".permission-checkbox, .menu-check-all, .child-check-all").prop(
        "checked",
        isChecked
    );
    $(".permission-item").toggleClass("checked", isChecked);
});

// Menu check all functionality
$(".menu-check-all").on("change", function () {
    const menuId = $(this).data("menu");
    const isChecked = $(this).is(":checked");

    $(`.menu-${menuId}`).prop("checked", isChecked);
    $(`.menu-${menuId}`).each(function () {
        updatePermissionItemState(this);
    });

    // Update child check all checkboxes
    $(`[data-child]`).each(function () {
        const childId = $(this).data("child");
        const childCheckboxes = $(`.child-${childId}`);
        if (
            childCheckboxes.length > 0 &&
            childCheckboxes.first().hasClass(`menu-${menuId}`)
        ) {
            $(this).prop("checked", isChecked);
        }
    });
});

// Child check all functionality
$(".child-check-all").on("change", function () {
    const childId = $(this).data("child");
    const isChecked = $(this).is(":checked");

    $(`.child-${childId}`).prop("checked", isChecked);
    $(`.child-${childId}`).each(function () {
        updatePermissionItemState(this);
    });
});

// Update parent checkboxes when individual permissions change
function updateParentCheckboxes() {
    // Update child check all checkboxes
    $(".child-check-all").each(function () {
        const childId = $(this).data("child");
        const childCheckboxes = $(`.child-${childId}`);
        const checkedChildCheckboxes = childCheckboxes.filter(":checked");

        if (checkedChildCheckboxes.length === childCheckboxes.length) {
            $(this).prop("checked", true);
        } else {
            $(this).prop("checked", false);
        }
    });

    // Update menu check all checkboxes
    $(".menu-check-all").each(function () {
        const menuId = $(this).data("menu");
        const menuCheckboxes = $(`.menu-${menuId}`);
        const checkedMenuCheckboxes = menuCheckboxes.filter(":checked");

        if (checkedMenuCheckboxes.length === menuCheckboxes.length) {
            $(this).prop("checked", true);
        } else {
            $(this).prop("checked", false);
        }
    });

    // Update main check all checkbox
    const allCheckboxes = $(".permission-checkbox");
    const allCheckedCheckboxes = allCheckboxes.filter(":checked");

    if (allCheckedCheckboxes.length === allCheckboxes.length) {
        $("#checkAll").prop("checked", true);
    } else {
        $("#checkAll").prop("checked", false);
    }
}

// Make entire header clickable for collapse
$(".menu-header").on("click", function () {
    const target = $(this).data("target");
    $(target).collapse("toggle");
});

// Prevent checkbox clicks from triggering collapse
$(".menu-select-all, .menu-check-all").on("click", function (e) {
    e.stopPropagation();
});

// Update collapse icon rotation
$(".menu-header").on("click", function () {
    const $icon = $(this).find(".collapse-icon");
    const target = $(this).data("target");

    $(target).on("shown.bs.collapse", function () {
        $icon.css("transform", "rotate(180deg)");
    });

    $(target).on("hidden.bs.collapse", function () {
        $icon.css("transform", "rotate(0deg)");
    });
});

// Initial state check
updateParentCheckboxes();
